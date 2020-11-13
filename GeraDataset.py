# Abre o arquivo  my_dataset.txt (leitura)
arquivo = open('my_dataset.txt', 'r')
conteudo = arquivo.readlines()

# Lista das Amostras Cercóspora
for i in range(1, 600):
    conteudo.append('cercosporaJPG/' + str(i) +'.jpg'+ ' 0'+ "\n" )
    arquivo = open('my_dataset.txt', 'w')
    arquivo.writelines(conteudo)

# Lista das imagens dos Ferrugem   
for i in range(1, 600):
    conteudo.append('ferrugemJPG/' + str(i) +'.jpg'+ ' 1'+ "\n" )
    arquivo = open('my_dataset.txt', 'w')
    arquivo.writelines(conteudo)

# Lista das imagens Saudaveis  
for i in range(1, 600):
    conteudo.append('saudaveisJPG/' + str(i) +'.jpg'+ ' 2'+ "\n" )
    arquivo = open('my_dataset.txt', 'w')
    arquivo.writelines(conteudo)

arquivo.close()
