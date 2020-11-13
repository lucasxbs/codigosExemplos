#!/usr/bin/env python

import cv2
import tflearn 
from tflearn.layers.conv import conv_2d, max_pool_2d
from tflearn.layers.core import input_data, dropout, fully_connected
from tflearn.layers.estimator import regression
from tflearn.data_preprocessing import ImagePreprocessing
from tflearn.data_augmentation import ImageAugmentation
from tflearn.data_utils import image_preloader
import numpy as np


dataset_file = 'my_dataset.txt'

ndims = input("GS or Color? ")
if ndims == "GS":
	ndims = 1
else:
	ndims = 3


#X, Y = image_preloader(dataset_file, image_shape=(64, 64), mode='file', categorical_labels=True, normalize=True)

if ndims == 1:
	print("Grayscale")
	X, Y = image_preloader(dataset_file, image_shape=(128, 128), mode='file', categorical_labels=True, normalize=True, grayscale = True)
else:
	print("Color")
	X, Y = image_preloader(dataset_file, image_shape=(128, 128), mode='file', categorical_labels=True, normalize=True, grayscale = False) # RGB

###
# O reshape é muito importante, pois garante que o input vai estar no formato 4D que o conv_2d precisa.
# As dimensões são [batch, altura, largura, nro_canais]
# No preloader, é possível converter pra grayscale todas as imagens. Basta ajustar o parâmetro grayscale como True/False 
# Ref: https://www.kaggle.com/andrewrona22/an-example-of-cnn-using-tensorflow

#X = np.reshape(X, [-1, 128, 128, 3]) # ---> pro caso de usar imagens RGB
X = np.reshape(X, [-1, 128, 128, ndims]) # ---> pro caso de usar imagens grayscale
###

img_prep = ImagePreprocessing()
img_prep.add_featurewise_zero_center()
img_prep.add_featurewise_stdnorm()

convnet = input_data(shape=[None, 128, 128, ndims], data_preprocessing=img_prep, name='input')
#convnet = input_data(shape=[None, 128, 128, 3], data_preprocessing=img_prep, name='input') # RGB


convnet = conv_2d(convnet, 42, 2, activation='relu')#32 -> 42
convnet = max_pool_2d(convnet,2)
convnet = conv_2d(convnet, 74, 2, activation='relu') #64 -> 74
convnet = max_pool_2d(convnet,2)


convnet = fully_connected(convnet, 612, activation='relu')
convnet = dropout(convnet, 0.2)

# Finalmente nosso layer de saida terá 3 classes: Cercospora, Ferrugem e imagens Negativas 
convnet = fully_connected(convnet, 3, activation='softmax')




# '''
# Fazemos regressão na rede convnet. É assim que o convnet será capaz de atualizar seus pesos e tentar melhorar sua precisão ao minimizar as perdas.
# Nós estabelecemos o nosso otimizador como 'adam' e o learning_rate como 0.001.
# A perda será calculada por 'categorical_crossentropy' e nós iremos nomear esta camada como "targets".
# Novamente você pode brincar com estes e ver quais resultados diferentes você obtém.
# Por exemplo, alterar o learning_rate de 0,01 a 0,001 pode melhorar dramaticamente minha precisão de treino neste caso.
# '''

convnet = regression(convnet, optimizer='adam', learning_rate=0.001, loss='categorical_crossentropy', name='targets')

model = tflearn.DNN(convnet, tensorboard_verbose=3, tensorboard_dir='Logs')

model.fit(X,Y, validation_set=0.1, n_epoch=10, snapshot_step=500, show_metric=True, run_id='cafe')

model.save('cafe.model')
