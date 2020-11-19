<?php
/**
 * 2020 point-barre.com
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @author    tenshy
 * @copyright 2020 point-barre.com
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
	// Vérifie que le module est bien exécuté au sein de Prestashop
	// en contrôlant que la constante _PS_VERSION est définie
	if (!defined('_PS_VERSION_')) {
	    exit;
	}

	class Pb_Quote extends Module
	{
		// Constructeur de la classe
		public function __construct()
	    {
	    	// Nom du module / identifiant interne
	        $this->name = 'pb_quote';
	        // Onglet dans lequel afficher le module dans la liste des modules installés
	        $this->tab = 'front_office_features';
	        // Version du module
	        $this->version = '1.0.0';
	        // Auteur
	        $this->author = 'tenshy';
	        // Indique s'il faut ou non créé une instance du module lors du chargement
	        // de la liste des modules de Prestashop
	        $this->need_instance = 0;
	        // Défini avec quelle version de Prestashop le module est compatible
	        $this->ps_versions_compliancy = [
	            'min' => '1.7',
	            'max' => _PS_VERSION_
	        ];
	        // Indique s'il faut utiliser le système de rendu Bootstrap pour ce module
        	$this->bootstrap = true;

        	// Exécute la méthode __construct de la classe parente 'Module'
	        parent::__construct();

	        // Nom affiché et description
	        $this->displayName = $this->l('point-barre Quote');
	        $this->description = $this->l('Replace invoice by quote');

	        // Texte de confirmation à la désinstallation
	        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module');

	        // Permet de vérifier si la variable définie plus tard dans l'administration
	        // est définie ou non. Si ce n'est pas le cas, affiche un avertissement
	        if (!Configuration::get('PB_QUOTE_PAGENAME')) {
	            $this->warning = $this->l('Aucun nom fourni');
	        }
	    }

	    // Méthode exécutée à l'installation
	    public function install()
		{
			// Vérifie si le mode multi-boutique de Prestashop 1.7 est activé
		    if (Shop::isFeatureActive()) {
		    	// Si oui, défini le contexte pour appliquer l’installation à toutes les boutiques	
		        Shop::setContext(Shop::CONTEXT_ALL);
		    }

		    // Lance l'installation qui va :
		    // - Installer le module en exécutant la méthode install de la classe parente
		    // - Exécuter les 2 méthodes d'enregistrement des hooks
		    // - Et ajouter la valeur PB_QUOTE_PAGENAME à la base de données
		    // Et récupère le résultat de toutes ces actions, si l'une d'entre elles échoue
		    // en renvoyant 'false', cela indique que l'installation a échouée
		    if (!parent::install() ||
		        /*!$this->registerHook('leftColumn') ||
		        !$this->registerHook('header') ||*/
		        !Configuration::updateValue('PB_QUOTE_PAGENAME', 'tenshy')) 
		    {
		        return false;
		    }

		    return true;
		}

		// Méthode exécutée à la désinstallation
		public function uninstall()
		{	
			// Lance la désinstallation qui va :
			// - Désinstaller le module en exécutant la méthode uninstall de la classe parente
			// - Et supprimer la valeur PB_QUOTE_PAGENAME de la base de données
			// Et récupère le résultat, si l'une d'entre elles renvoie 'false', l'installation a échouée
		    if (!parent::uninstall() ||
		        !Configuration::deleteByName('PB_QUOTE_PAGENAME')) 
		    {
		        return false;
		    }

		    return true;
		}

		// Méthode utilisée pour afficher du contenu dans la page de configuration du module
	    public function getContent()
		{
		    $output = null;
		 	
		 	// Vérifie si le formulaire a été envoyé en fonction du nom du bouton, ici appelé btnSubmit
		    if (Tools::isSubmit('btnSubmit')) {
		    	// Récupère la valeur du champ PB_QUOTE_PAGENAME
		        $pageName = strval(Tools::getValue('PB_QUOTE_PAGENAME'));
		 		
		 		// Vérifie qu'il existe et qu'il ne soit pas vide
		        if (
		            !$pageName||
		            empty($pageName)
		        ) {
		        	// Si c'est le cas, affiche une erreur de validation
		            $output .= $this->displayError($this->l('Invalid Configuration value'));
		        } else {
		        	// Sinon met à jour la valeur de PB_QUOTE_PAGENAME
		            Configuration::updateValue('PB_QUOTE_PAGENAME', $pageName);
		            // Et notifie l'utilisateur de la modification
		            $output .= $this->displayConfirmation($this->l('Settings updated'));
		        }
		    }
		 	
		 	// Affiche le formulaire
		    return $output.$this->displayForm();
		}

		public function displayForm()
		{
		    // Récupère la langue par défaut
		    $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');
		 
		    // Initialise les champs du formulaire dans un tableau
		    $form = array(
		    	'form' => array(
			        'legend' => array(
			            'title' => $this->l('Settings'),
			        ),
			        'input' => array(
		            	array(
			                'type' => 'text',
			                'label' => $this->l('Configuration value'),
			                'name' => 'PB_QUOTE_PAGENAME',
			                'size' => 20,
			                'required' => true
			            )
			        ),
			        'submit' => array(
			            'title' => $this->l('Save'),
			            'name'  => 'btnSubmit'
			        )
			    ),
			);
		 	
		    $helper = new HelperForm();
		 
		    // Module, token et currentIndex
		    $helper->module = $this;
		    $helper->name_controller = $this->name;
		    $helper->token = Tools::getAdminTokenLite('AdminModules');
		    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		 
		    // Langue
		    $helper->default_form_language = $defaultLang;	
		    $helper->allow_employee_form_lang = $defaultLang;
		 
		    // Charge la valeur de PB_QUOTE_PAGENAME depuis la base
		    $helper->fields_value['PB_QUOTE_PAGENAME'] = Configuration::get('PB_QUOTE_PAGENAME');
		 	
		 	// Génère le formulaire sur la base des informations données
		    return $helper->generateForm(array($form));
		}

/*		// Hook utilisé pour afficher du contenu dans la colonne de gauche
		public function hookDisplayLeftColumn($params)
		{
		 	// Assigne les variables smarty à utiliser dans la vue :
		 	// - La valeur de PB_QUOTE_PAGENAME
		 	// - Le lien à suivre
		    $this->context->smarty->assign([
		        'pb_quote_page_name' => Configuration::get('PB_QUOTE_PAGENAME'),
		        'pb_quote_page_link' => $this->context->link->getModuleLink('pb_quote', 'display')
		      ]);

		    // Affiche le template pb_quote.tpl en tenant compte des variables smarty
		    // assignée au dessus
		    return $this->display(__FILE__, 'pb_quote.tpl');
		}

		// Hook utilisé pour afficher du contenu dans l'en-tête
		public function hookDisplayHeader()
		{
			// Dans l'en-tête, ajoute la feuille de style spécifique à notre module
			$this->context->controller->registerStylesheet(
		        'pb_quote',
		        $this->_path.'views/css/pb_quote.css',
		        ['server' => 'remote', 'position' => 'head', 'priority' => 150]
		    );
		}
*/
	}
