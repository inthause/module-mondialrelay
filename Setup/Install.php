<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

namespace Rbs\Mondialrelay\Setup;

/**
 * @name \Rbs\Mondialrelay\Setup\Install
 */
class Install extends \Change\Plugins\InstallBase
{
	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param \Change\Application $application
	 * @param \Change\Configuration\EditableConfiguration $configuration
	 * @throws \RuntimeException
	 */
	public function executeApplication($plugin, $application, $configuration)
	{
		$configuration->addPersistentEntry('Rbs/Geo/Events/GeoManager/Rbs_Mondialrelay', '\Rbs\Mondialrelay\Events\GeoManager\Listeners');
	}

	public function executeServices($plugin, $applicationServices)
	{
		parent::executeServices($plugin, $applicationServices);

		$applicationServices->getDocumentCodeManager();

		$import = new \Rbs\Generic\Json\Import($applicationServices->getDocumentManager());
		$import->setDocumentCodeManager($applicationServices->getDocumentCodeManager());

		$json = json_decode(file_get_contents(__DIR__ . '/Assets/AddressFields.json'), true);
		try
		{
			$applicationServices->getTransactionManager()->begin();
			$import->fromArray($json);
			$applicationServices->getTransactionManager()->commit();
		}
		catch (\Exception $e)
		{
			$applicationServices->getTransactionManager()->rollBack($e);
		}
	}
}
