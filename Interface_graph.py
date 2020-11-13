
'''--------------------------------------------------------------
                        Simples Interface GRAFICA GUI
                        usando TKinter e OpenCV
                        07/03/2019
                        Versão que roda em Python 3.5
        
-----------------------------------------------------------------'''

# importa os pacotes necessarios
from tkinter import *
from PIL import Image
from PIL import ImageTk
from tkinter import filedialog
from tkinter import *
import tkinter.filedialog
import cv2

#importa os pacotes do Deep Learning
import tflearn 
from tflearn.layers.conv import conv_2d, max_pool_2d
from tflearn.layers.core import input_data, dropout, fully_connected
from tflearn.layers.estimator import regression
from tflearn.data_preprocessing import ImagePreprocessing
from tflearn.data_augmentation import ImageAugmentation
from tflearn.data_utils import image_preloader, shuffle
import scipy
from skimage import io
from skimage.transform import resize
from skimage.color import rgb2gray, gray2rgb
import numpy as np

#---------------------   Carrega a Rede DeepLearning que foi criada
dataset_file = 'my_dataset.txt'


ndims = input("GS or Color? ")
if ndims == "GS":
        ndims = 1
else:
        ndims = 3

dataset_file = 'my_dataset.txt'
X_test, Y_test = image_preloader(dataset_file, image_shape=(128, 128), mode='file', categorical_labels=True, normalize=True)
X_test = np.reshape(X_test, (-1, 128, 128,ndims))


img_prep = ImagePreprocessing()
img_prep.add_featurewise_zero_center()
img_prep.add_featurewise_stdnorm()

convnet = input_data(shape=[None,128,128,ndims], data_preprocessing=img_prep, name='input')


convnet = conv_2d(convnet, 42, 2, activation='relu')#32 -> 42
convnet = max_pool_2d(convnet,2)
convnet = conv_2d(convnet, 74, 2, activation='relu') #64 -> 74
convnet = max_pool_2d(convnet,2)



convnet = fully_connected(convnet, 612, activation='relu')
convnet = dropout(convnet, 0.2)
convnet = fully_connected(convnet,3,activation='softmax')
convnet = regression(convnet, optimizer='adam', learning_rate=0.001, loss='categorical_crossentropy', name='targets')
model = tflearn.DNN(convnet)
model.load('cafe.model')
#--------------------------------------------------------

#-------  Abre a imagem usando a a interface grafica que foi
#------   criada com o TKinter  -------------------------------
def select_image():
        # Pega uma referencia para os paineis de imagens
        global panelA, panelB

        
# Abre uma caixa de diálogo de seleção de arquivos e permite que o usuário selecione uma entrada
# Imagem
        path = tkinter.filedialog.askopenfilename()

        class_dict = {0: 'cercospora', 1: 'ferrugem', 2: 'saudavel'} # dicionario relacionando o indice do vetor de probabilidades com sua classe correspondente

        # assegura que algum arquivo foi selecionado
        if len(path) > 0:
                # abre uma imagem do disco, converte para escala de cinzas e 
                # detecta bordas
                image = cv2.imread(path)
                adv = image

                # Coloca imagem que nÃ³s queremos classificar
                #x = io.imread(path).reshape((128, 128, 3)).astype(np.float)
                
                x = io.imread(path)
                if ndims == 1:
                        x = rgb2gray(x)
                x = resize(x, (128, 128)).reshape((128, 128, ndims)).astype(np.float)
                x = np.reshape(x, [-1, 128, 128, ndims])
                
                # Checa o resultado
                #result = model.predict([x])[0]    # Faz a Previsao
                result = model.predict(x)[0]    # Faz a Previsao

        
                # Mostra o indice do maior elemento do vetor RESULT
                print("Vetor de probabilidades de cada classe:\n", np.round(result, 2))
                n_max = np.argmax(result) # a funcao argmax retorna o indice do maior valor em um vetor
                print("Classe: ", class_dict[n_max])
                print("\n")


# inicia a ferramenta que vai mostrar as duas janelas lado a lado
root = Tk()
panelA = None
panelB = None


# Cria um botao que quando pressionado, desencadeia um seletor de arquivos
# E permite ao usuário selecionar uma imagem de entrada; Em seguida, adiciona o
# Botão GUI
btn = Button(root, text="Escolha a imagem", command=select_image, height = 10, width = 35)
btn.pack(side="bottom", fill="both", expand="yes", padx="30", pady="30")
# sai fora da interface GUI
root.mainloop()


