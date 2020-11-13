''''----------------------------------------------------------------------
Idenficador de Imagens
07/03/2019
-------------------------------------------------------------------------'''

import tflearn 
from tflearn.layers.conv import conv_2d, max_pool_2d
from tflearn.layers.core import input_data, dropout, fully_connected
from tflearn.layers.estimator import regression
from tflearn.data_preprocessing import ImagePreprocessing
from tflearn.data_augmentation import ImageAugmentation
from tflearn.data_utils import image_preloader, shuffle
import cv2
from skimage import io
import numpy as np

dataset_file = 'my_dataset.txt'

X_test, Y_test = image_preloader(dataset_file, image_shape=(128, 128), mode='file', categorical_labels=True, normalize=True)

X_test = np.reshape(X_test, (-1, 128, 128,3))



X_test = cv2.imread('1.bmp')
x = io.imread(X_test).reshape((128, 128, 3)).astype(np.float) / 255


img_prep = ImagePreprocessing()
img_prep.add_featurewise_zero_center()
img_prep.add_featurewise_stdnorm()


convnet = input_data(shape=[None,128,128,3], data_preprocessing=img_prep, name='input')
convnet = conv_2d(convnet, 32, 2, activation='relu')
convnet = max_pool_2d(convnet,2)
convnet = conv_2d(convnet, 64, 2, activation='relu')
convnet = max_pool_2d(convnet,2)
convnet = fully_connected(convnet, 512, activation='relu')
convnet = dropout(convnet, 0.6)

convnet = fully_connected(convnet,4,activation='softmax')

convnet = regression(convnet, optimizer='adam', learning_rate=0.001, loss='categorical_crossentropy', name='targets')

model = tflearn.DNN(convnet)

model.load('cafe.model')

# Predict
prediction = model.predict(x)

#print (prediction)

# FAZ A CLASSIFICACAO
#-------------------------------------------------------
# Mostra o indice do maior elemento do vetor RESULT

contador=0
numero_amostras=len(prediction)

for j in range(len(prediction)):
    indice_real=np.argmax(Y_test[j])

    if (indice_real==0):
        print ("cercospora")

    if (indice_real==1):
        print ("ferrugem")
                                     
    if (indice_real==2):
        print ("saudavel")
                                
   
    vector=prediction[j]
    m=0
    n_max=0
    for i in range(len(vector)):
        if vector[i]>=m:
            n_max = i
            m = vector[i]    
    if (n_max==0):
        print ("Previsao: Imagem de folha com cercospora")

    if (n_max==1):
        print ("Previsao: Imagem de folha com ferrugem")
                                     
    if (n_max==2):
        print ("Previsao: Imagem de folha saudavel")
                                
    if (indice_real==n_max):
        contador=contador+1

Taxa=100*(contador/numero_amostras)

print(Taxa)

 

