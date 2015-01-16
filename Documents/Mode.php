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
	/**
	 * @return string
	 */
	public function getCategory()
	{
		return static::CATEGORY_RELAY;
	}

	public function onDefaultGetModeData(Event $event)
	{
		parent::onDefaultGetModeData($event);
		$modeData = $event->getParam('modeData');
		$modeData['editor'] = [
			'titleLayer' => 'OSM',
			'defaultLatitude' => 48.856578,
			'defaultLongitude' => 2.351828,
			'defaultZoom' => 11
		];
		$event->setParam('modeData', $modeData);
	}

	/**
	 * @return string
	 */
	public function getWSUrl()
	{
		return 'http://api.mondialrelay.com/Web_Services.asmx?WSDL';
	}

}
