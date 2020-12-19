#!/usr/bin/python3
# -*- coding: iso-8859-15 -*-

import sys, getopt, csv
from math import *

def removeDuplicate(l):
    seen = set()
    new_l = []
    for d in l:
        t = tuple(d.items())
        if t not in seen:
            seen.add(t)
            new_l.append(d)
    return new_l

def getCategory(r):
    category = []
    #if row['libellé de la nomenclature']:
        #category.append(row['libellé de la nomenclature'])
    if row['libellé de la sous famille de base']:
        category.append(row['libellé de la sous famille de base'].replace('/', '-').replace(',', '-'))
    if row['libellé de la sous famille']:
        category.append(row['libellé de la sous famille'].replace('/', '-').replace(',', '-'))
    if row['libellé de la famille']:
        category.append(row['libellé de la famille'].replace('/', '-').replace(',', '-'))
    if row['libellé de la spécialité']:
        category.append(row['libellé de la spécialité'].replace('/', '-').replace(',', '-'))
    return ','.join(category)
    
def toMonetary(number, digits=2):
    pown = pow(10, digits)
    pown1 = pow(10, digits+1)
    r = fmod(number*pown1,10)
    if r > 5:
        return ceil(number*pown)/pown
    else:
        return floor(number*pown)/pown

VAT = 1.055
    
def computePriceHT(price, margin, a, vat=VAT):
    ht = computePriceTTC(price, margin, a, vat) / vat
    return toMonetary(ht)

def computePriceTTC(price, margin, a, vat=VAT):
    ttc100 = (price * (100+margin)) * vat
    ttc100 = ttc100 + 100*a - fmod(ttc100,100*a)
    return toMonetary(ttc100 / 100)

def writeCsvFile(fileName, fieldnames, rows):
    with open(fileName, 'w', newline='', encoding='iso-8859-1') as fout:
        writer = csv.DictWriter(fout, fieldnames=fieldnames, restval='',delimiter=';')
        writer.writeheader()
        writer.writerows(rows)


#Default values
shop='itsame'
margin = 30
arrondi = 1
batchsize = 10000
maxProducts = -1
offset = -1

inputfile = sys.argv[1]

try:
    opts, args = getopt.getopt(sys.argv[2:],"hm:b:a:s:",["marge=","boutique=","arrondi=","batchsize=","max=", "offset="])
except getopt.GetoptError:
    print('parseCedeo.py <inputfile> [-m <marge>] [-b <boutique>] [-a <arrondi>] [-s <batchsize>]')
    print('boutique par défaut: '+shop)
    print('marge par défaut: '+str(margin))
    print('arrondi par défaut: '+str(arrondi))
    print('batchsize par défaut: '+str(batchsize))
    sys.exit(2)
for opt, arg in opts:
    if opt == '-h':
        print('parseCedeo.py <inputfile> [-m <marge>] [-b <boutique>] [-a <arrondi>] [-s <batchsize>]')
        print('boutique par défaut: '+shop)
        print('marge par défaut: '+str(margin))
        print('arrondi par défaut: '+str(arrondi))
        print('batchsize par défaut: '+str(batchsize))
        sys.exit()
    elif opt in ("-m", "--marge"):
        margin = int(arg)
    elif opt in ("-b", "--boutique"):
        shop = arg
    elif opt in ("-a", "--arrondi"):
        arrondi = float(arg)
    elif opt in ("-s", "--batchsize"):
        batchsize = int(arg)
    elif opt in ("--max"):
        maxProducts = int(arg)
    elif opt in ("--offset"):
        offset = int(arg)
        
#c = 0
#infini = 1000000
#for x in range(infini):
    #price = (x+1)/100
    #ht = computePrice(price, margin, arrondi)
    #print(str(price) + ' - ' + str(ht) + ' - ' + str(toMonetary(ht*1.055)))
    #c = c + 100*ht/price - 100
#print(toMonetary(c/infini))
#sys.exit()

count = 0
fileCount = 1

cfieldnames = ['Nom', 'Actif', 'Racine', 'Parent', 'Boutique']
mfieldnames = ['Nom', 'Actif', 'Boutique']
pfieldnames = ['Nom', 
               'Actif', 
               'Categorie', 
               'Prix achat HT', 
               'Prix public HT', 
               'Marque', 
               'Fournisseur', 
               'Ref fournisseur', 
               'Ref', 
               'EAN', 
               #'Prix HT', 
               #'Prix TTC', 
               'Id TVA', 
               'Boutique']

with open(inputfile, newline='', encoding='iso-8859-1') as fin:
    categories = []
    products = []
    manufacturers = []
    categories.append({
                    'Nom': 'Catégories',
                    'Actif': 1,
                    'Racine': 1, 
                    'Parent': 'Accueil', 
                    'Boutique': shop})
    for row in csv.DictReader(fin, delimiter=';'):
        price = row["prix net HT du client dans l''agence"]
        public_price = row["prix public HT"]
        if price != public_price:
            if count < offset:
                continue
            if row['libellé de la spécialité']:
                categories.append({
                    'Nom': row['libellé de la spécialité'].replace('/', '-').replace(',', '-'),
                    'Actif': 1,
                    'Racine': 0, 
                    'Parent': 'Catégories', 
                    'Boutique': shop})
                if row['libellé de la famille']:
                    if row['libellé de la famille'] != row['libellé de la spécialité']:
                        categories.append({
                            'Nom': row['libellé de la famille'].replace('/', '-').replace(',', '-'),
                            'Actif': 1,
                            'Racine': 0,
                            'Parent': row['libellé de la spécialité'].replace('/', '-').replace(',', '-'), 
                            'Boutique': shop})
                    if row['libellé de la sous famille'] :
                        if row['libellé de la famille'] != row['libellé de la sous famille']:
                            categories.append({
                                'Nom': row['libellé de la sous famille'].replace('/', '-').replace(',', '-'),
                                'Actif': 1,
                                'Racine': 0,
                                'Parent': row['libellé de la famille'].replace('/', '-').replace(',', '-'), 
                                'Boutique': shop})
                        if row['libellé de la sous famille de base']:
                            if row['libellé de la sous famille de base'] != row['libellé de la sous famille']:
                                categories.append({
                                    'Nom': row['libellé de la sous famille de base'].replace('/', '-').replace(',', '-'),
                                    'Actif': 1,
                                    'Racine': 0,
                                    'Parent': row['libellé de la sous famille'].replace('/', '-').replace(',', '-'), 
                                    'Boutique': shop})
                            #if row['libellé de la nomenclature']:
                                #if row['libellé de la sous famille de base'] != row['libellé de la nomenclature']:
                                    #categories.append({
                                        #'Nom': row['libellé de la nomenclature'],
                                        #'Actif': 1,
                                        #'Racine': 0,
                                        #'Parent': row['libellé de la sous famille de base'], 
                                        #'Boutique': shop})
            manufacturers.append({
                'Nom':row['nom de la marque'],
                'Actif': 1, 
                'Boutique': shop})
            productName = row["libellé de l''article"].replace('>', '').replace('<', '').replace('{', '(').replace('}', ')').replace('/', '-').replace('#', '').replace(';', '-').replace('"', '').replace('=', '')
            if productName != productName[:128]:
                productName = productName[:125]+'...'
            ean = row['code EAN']
            if ean == 'null' or len(ean) != 13:
                ean=''
            products.append({
                'Nom':productName,
                'Actif': 1, 
                'Categorie': getCategory(row), 
                'Prix achat HT': price, 
                'Prix public HT': public_price,
                'Marque': row['nom de la marque'], 
                'Fournisseur': row["nom de l''enseigne"], 
                'Ref fournisseur': row["code de l''article"], 
                'Ref': row['référence de la marque'], 
                'EAN': ean, 
                #'Prix HT': computePriceHT(float(price), margin, arrondi), 
                #'Prix TTC': computePriceTTC(float(price), margin, arrondi), 
                'Id TVA': 2, 
                'Boutique': shop})
            
            count = count+1
            if count%batchsize == 0:
                writeCsvFile('products-'+str(fileCount)+'-'+shop+'.csv', pfieldnames, products)
                products = []
                fileCount = fileCount+1
            if count == maxProducts:
                break
    
    writeCsvFile('categories-'+shop+'.csv', cfieldnames, removeDuplicate(categories))
    
    writeCsvFile('manufacturers-'+shop+'.csv', mfieldnames, removeDuplicate(manufacturers))
    
    writeCsvFile('products-'+str(fileCount)+'-'+shop+'.csv', pfieldnames, products)


