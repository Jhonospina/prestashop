<?php
/*
* 2007-2011 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 7465 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class AdminDbControllerCore extends AdminController
{
	public function __construct()
	{
		$this->className = 'Configuration';
		$this->table = 'configuration';

		parent::__construct();

		$this->options = array(
			'database' => array(
				'title' =>	$this->l('Database'),
				'icon' =>	'database_gear',
				'class' =>	'width2',
				'fields' =>	array(
				 	'db_server' => array('title' => $this->l('Server:'), 'desc' => $this->l('IP or server name; \'localhost\' will work in most cases'), 'size' => 30, 'type' => 'text', 'required' => true, 'defaultValue' => _DB_SERVER_, 'visibility' => Shop::CONTEXT_ALL),
					'db_name' => array('title' => $this->l('Database:'), 'desc' => $this->l('Database name (e.g., \'prestashop\')'), 'size' => 30, 'type' => 'text', 'required' => true, 'defaultValue' => _DB_NAME_, 'visibility' => Shop::CONTEXT_ALL),
					'db_prefix' => array('title' => $this->l('Prefix:'), 'size' => 30, 'type' => 'text', 'defaultValue' => _DB_PREFIX_, 'visibility' => Shop::CONTEXT_ALL),
					'db_user' => array('title' => $this->l('User:'), 'size' => 30, 'type' => 'text', 'required' => true, 'defaultValue' => _DB_USER_, 'visibility' => Shop::CONTEXT_ALL),
					'db_passwd' => array('title' => $this->l('Password:'), 'size' => 30, 'type' => 'password', 'desc' => $this->l('Leave blank if no change'), 'defaultValue' => _DB_PASSWD_, 'visibility' => Shop::CONTEXT_ALL),
				),
				'submit' => array()
			),
		);

		$this->fieldsDisplay = array (
			'table' => array('title' => $this->l('Table'), 'type' => 'string', 'width' => 120),
			'table_engine' => array('title' => $this->l('Table Engine'), 'type' => 'string', 'width' => 120),
		);
	}

	public function initContent()
	{
		$this->warnings[] = $this->l('Be VERY CAREFUL with these settings, as changes may cause your PrestaShop online store to malfunction. For all issues, check the config/settings.inc.php file.');

		$this->content .= $this->initToolbar();
		$this->content .= $this->initOptions();

		$table_status = $this->getTablesStatus();
		foreach ($table_status as $key => $table)
			if (!preg_match('#^'._DB_PREFIX_.'.*#Ui', $table['Name']))
				unset($table_status[$key]);

		$this->context->smarty->assign(array(
			'update_url' => self::$currentIndex.'&submitAdd'.$this->table.'=1&token='.$this->token,
			'table_status' => $table_status,
			'engines' => $this->getEngines(),
			'content' => $this->content,
		));

	}

	public function postProcess()
	{
		// PrestaShop demo mode
		if (_PS_MODE_DEMO_)
		{
			$this->_errors[] = Tools::displayError('This functionnality has been disabled.');
			return;
		}

		if ($this->action == 'update_options')
		{
			foreach ($this->options['database']['fields'] AS $field => $values)
				if (isset($values['required']) AND $values['required'])
					if (($value = Tools::getValue($field)) == false AND (string)$value != '0')
						$this->_errors[] = Tools::displayError('field').' <b>'.$values['title'].'</b> '.Tools::displayError('is required.');

			if (!sizeof($this->_errors))
			{
				/* Datas are not saved in database but in config/settings.inc.php */
				$settings = array();
			 	foreach ($this->options['database']['fields'] as $k => $data)
					if ($value = Tools::getValue($k))
						$settings['_'.Tools::strtoupper($k).'_'] = $value;

				if (Db::checkConnection(
					isset($settings['_DB_SERVER_']) ? $settings['_DB_SERVER_'] : _DB_SERVER_,
					isset($settings['_DB_USER_']) ? $settings['_DB_USER_'] : _DB_USER_,
					isset($settings['_DB_PASSWD_']) ? $settings['_DB_PASSWD_'] : _DB_PASSWD_,
					isset($settings['_DB_NAME_']) ? $settings['_DB_NAME_'] : _DB_NAME_,
					true
				) == 0)
				{
			 		rewriteSettingsFile(NULL, NULL, $settings);
			 		Tools::redirectAdmin(self::$currentIndex.'&conf=6'.'&token='.$this->token);
				}
				else
					$this->_errors[] = Tools::displayError('Unable to connect to a database with these identifiers.');
			}
		}

		// Change engine
		if ($this->action == 'save')
		{
			if (!isset($_POST['tablesBox']) OR !sizeof($_POST['tablesBox']))
				$this->_errors[] = Tools::displayError('You did not select any tables');
			else
			{
				$available_engines = $this->getEngines();
				$tables_status = $this->getTablesStatus();
				$tables_engine = array();

				foreach ($tables_status AS $table)
					$tables_engine[$table['Name']] = $table['Engine'];

				$engineType = pSQL(Tools::getValue('engineType'));

				/* Datas are not saved in database but in config/settings.inc.php */
				$settings = array('_MYSQL_ENGINE_' => $engineType);
			    rewriteSettingsFile(NULL, NULL, $settings);

				foreach ($_POST['tablesBox'] AS $table)
				{
					if ($engineType == $tables_engine[$table])
						$this->_errors[] = $table.' '.$this->l('is already in').' '.$engineType;
					else
						if (!Db::getInstance()->execute('ALTER TABLE `'.bqSQL($table).'` ENGINE=`'.bqSQL($engineType).'`'))
							$this->_errors[] = $this->l('Can\'t change engine for').' '.$table;
						else
							echo '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />'.$this->l('Engine change of').' '.$table.' '.$this->l('to').' '.$engineType.'</div>';
				}
			}
		}

	}

	public function getEngines()
	{
		$engines = Db::getInstance()->executeS('SHOW ENGINES');
		$allowed_engines = array();
		foreach ($engines AS $engine)
		{
			if (in_array($engine['Engine'], array('InnoDB', 'MyISAM')) AND in_array($engine['Support'], array('DEFAULT', 'YES')))
				$allowed_engines[] = $engine['Engine'];
		}
		return $allowed_engines;
	}

	public function getTablesStatus()
	{
		return Db::getInstance()->executeS('SHOW TABLE STATUS');
	}
}


