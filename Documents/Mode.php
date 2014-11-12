<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

namespace Rbs\Mondialrelay\Documents;

use Change\Documents\Events\Event;

/**
 * @name \Rbs\Mondialrelay\Documents\Mode
 */
class Mode extends \Compilation\Rbs\Mondialrelay\Documents\Mode
{

	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach('httpInfos', [$this, 'onDefaultHttpInfos'], 5);
	}

	/**
	 * @param Event $event
	 */
	public function onDefaultHttpInfos(Event $event)
	{
		$httpInfos = $event->getParam('httpInfos',[]);
		$httpInfos['directiveName'] = 'rbs-commerce-shipping-mode-configuration-mondialrelay';
		$event->setParam('httpInfos', $httpInfos);
	}

	/**
	 * @return string
	 */
	public function getWSUrl()
	{
		return 'http://api.mondialrelay.com/Web_Services.asmx?WSDL';
	}

}
