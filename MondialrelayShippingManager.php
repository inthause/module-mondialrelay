<?php
/**
 * Copyright (C) 2014 Proximis
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

namespace Rbs\Mondialrelay;

/**
 * @name \Rbs\Mondialrelay\MondialrelayShippingManager
 */

class MondialrelayShippingManager
{
	/**
	 * Default context params:
	 *  - data:
	 *    - address:
	 *       - country
	 *       - zipCode
	 *       - city
	 *    - position:
	 *       - latitude
	 *       - longitude
	 *    - options:
	 *       - modeId
	 *    - matchingZone: string or array
	 * @param \Change\Events\Event $event
	 */
	public function onGetPoints($event)
	{
		$points = $event->getParam('points');
		if (is_array($points))
		{
			return;
		}

		$genericServices = $event->getServices('genericServices');
		if (!($genericServices instanceof \Rbs\Generic\GenericServices))
		{
			return;
		}

		$context = $event->getParam('context') + ['data' => ['address' => [], 'position' => [], 'options' => [], 'matchingZone' => null]];
		$data = $context['data'];

		if (isset($data['options']['modeId']) && is_numeric($data['options']['modeId']))
		{
			$matchingZone = $data['matchingZone'];
			$documentManager = $event->getApplicationServices()->getDocumentManager();
			$mode = $documentManager->getDocumentInstance($data['options']['modeId']);
			if ($mode instanceof \Rbs\Mondialrelay\Documents\Mode)
			{

				$geoManager = $genericServices->getGeoManager();
				$addressFields = $mode->getAddressFields();
				if (!$addressFields)
				{
					$event->getApplication()->getLogging()->error('AddressFields not defined on: ' . $mode . ', ' . $mode->getLabel());
					return;
				}
				$points = [];
				$clientOptions = array('encoding' => 'utf-8', 'trace' => true);
				$soapClient = new \SoapClient($mode->getWSUrl(), $clientOptions);

				$params = array(
					'Enseigne' => $mode->getVendorcode(),
					'Pays' => isset($data['address']['country']) ? $data['address']['country'] : "FR",
					'Ville' => isset($data['address']['city']) ? $data['address']['city'] : "",
					'CP' => isset($data['address']['zipCode']) ? $data['address']['zipCode'] : "",
					'Latitude' => isset($data['position']['latitude']) ? $data['position']['latitude'] : "",
					'Longitude' => isset($data['position']['longitude']) ? $data['position']['longitude'] : "",
					'Taille' => "",
					'Poids' => isset($data['options']['weight']) ? $data['options']['weight'] : "",
					'Action' => $mode->getAction() ? $mode->getAction() : "",
					'DelaiEnvoi' => $mode->getDelay() ? $mode->getDelay() : "0",
					'RayonRecherche' => $mode->getSearchradius() ? $mode->getSearchradius() : ""
				);
				$params["Security"] = $this->generateSecurityKey($params, $mode->getVendorprivatekey());
				$resultSoap = null;
				try
				{
					$resultSoap = $soapClient->WSI3_PointRelais_Recherche($params);
					if ($resultSoap != null)
					{
						$result = $resultSoap->WSI3_PointRelais_RechercheResult;
						$status = $result->STAT;
						if ($status == '0')
						{
							$i18nManager = $event->getApplicationServices()->getI18nManager();
							$mondayTitle = $i18nManager->trans('c.date.long_day_name_monday');
							$tuesdayTitle = $i18nManager->trans('c.date.long_day_name_tuesday');
							$wednesdayTitle = $i18nManager->trans('c.date.long_day_name_wednesday');
							$thursdayTitle = $i18nManager->trans('c.date.long_day_name_thursday');
							$fridayTitle = $i18nManager->trans('c.date.long_day_name_friday');
							$saturdayTitle = $i18nManager->trans('c.date.long_day_name_saturday');
							$sundayTitle = $i18nManager->trans('c.date.long_day_name_sunday');

							$list = $result->PointsRelais->PointRelais_Details;
							$index = 0;
							foreach ($list as $item)
							{
								$addressData = [
									'__addressFieldsId' => $addressFields->getId(),
									\Rbs\Geo\Address\AddressInterface::COUNTRY_CODE_FIELD_NAME => $item->Pays,
									\Rbs\Geo\Address\AddressInterface::ZIP_CODE_FIELD_NAME => $item->CP,
									\Rbs\Geo\Address\AddressInterface::LOCALITY_FIELD_NAME => trim($item->Ville),
									'LgAdr1' => trim($item->LgAdr1),
									'LgAdr2' => trim($item->LgAdr2),
									'LgAdr3' => trim($item->LgAdr3),
									'LgAdr4' => trim($item->LgAdr4),
									'Num' => $item->Num,
									'Latitude' => str_replace(',', '.', $item->Latitude),
									'Longitude' => str_replace(',', '.', $item->Longitude),
								];

								$address = new \Rbs\Geo\Address\BaseAddress($addressData);
								$checkMatchingZone = $this->checkMatchingAddress($address, $matchingZone, $geoManager);
								if (!$checkMatchingZone)
								{
									continue;
								}

								$point = new \Rbs\Geo\Map\Point();
								$point->setTitle(trim($item->LgAdr1));
								$point->setCode($item->Num);
								$point->setLatitude(floatval(str_replace(',', '.', $item->Latitude)));
								$point->setLongitude(floatval(str_replace(',', '.', $item->Longitude)));

								$letter = chr(ord('A') + $index);



								$address->setLines($geoManager->getFormattedAddress($address));
								$point->setAddress($address);

								$options = [
									'matchingZone' => $checkMatchingZone,
									'letter' => $letter,
									'iconUrl' => '/Theme/Rbs/Base/Rbs_Mondialrelay/img/pr-' . $letter . '.png',
									'distance' => $item->Distance,
									'activityType' => $item->TypeActivite,
									'localisation1' => trim($item->Localisation1),
									'localisation2' => trim($item->Localisation2),
									'mapUrl' => $item->URL_Plan,
									'timeSlot' => [
										['dayName' => $mondayTitle, 'schedule' => $this->formatHours($item->Horaires_Lundi->string)],
										['dayName' => $tuesdayTitle, 'schedule' => $this->formatHours($item->Horaires_Mardi->string)],
										['dayName' => $wednesdayTitle, 'schedule' => $this->formatHours($item->Horaires_Mercredi->string)],
										['dayName' => $thursdayTitle, 'schedule' => $this->formatHours($item->Horaires_Jeudi->string)],
										['dayName' => $fridayTitle, 'schedule' => $this->formatHours($item->Horaires_Vendredi->string)],
										['dayName' => $saturdayTitle, 'schedule' => $this->formatHours($item->Horaires_Samedi->string)],
										['dayName' => $sundayTitle, 'schedule' => $this->formatHours($item->Horaires_Dimanche->string)]
									]
								];

								if ($item->URL_Photo)
								{
									$options['pictureUrl'] = $item->URL_Photo;
								}
								else
								{
									$options['pictureUrl'] = 'https://www.mondialrelay.fr/img/dynamique/pr.aspx?id=' . $item->Pays
										. str_pad($point->getCode(), 6, "0", STR_PAD_LEFT);
								}

								$point->setOptions($options);
								$points[] = $point->toArray();
								$index++;
							}
						}
					}
				}
				catch (\Exception $e)
				{
					$event->getApplication()->getLogging()->error('SOAP CALL Fail : ' . $e->getMessage());
				}
				$event->setParam('points', $points);
			}
		}
	}

	/**
	 * @param \Rbs\Geo\Address\AddressInterface $address
	 * @param string|string[] $matchingZone
	 * @param \Rbs\Geo\GeoManager $geoManager
	 * @return boolean
	 */
	protected function checkMatchingAddress($address, $matchingZone, $geoManager)
	{
		if (!$matchingZone)
		{
			return true;
		}
		elseif (is_string($matchingZone))
		{
			$match = true;
			$zone = $geoManager->getZoneByCode($matchingZone);
			if ($zone)
			{
				$match = $geoManager->isValidAddressForZone($address, $matchingZone);
			}
			return $match ? $matchingZone : false;
		}
		elseif (is_array($matchingZone))
		{
			$match = false;
			foreach ($matchingZone as $zone)
			{
				if (is_string($zone))
				{
					$match = $this->checkMatchingAddress($address, $zone, $geoManager);
					if ($match)
					{
						break;
					}
				}
			}
			return $match;
		}
		return false;
	}

	protected function formatHours($hoursOfDay)
	{
		$timeSlot = [];
		if ($hoursOfDay[0] != '0000')
		{
			$beginHour = intval(substr($hoursOfDay[0], 0, 2));
			$endHour = intval(substr($hoursOfDay[1], 0, 2));
			if ($beginHour > 12)
			{
				$timeSlot[0] = null;
				$timeSlot[1] = [$this->formatHour($hoursOfDay[0]), $this->formatHour($hoursOfDay[1])];
			}
			else
			{
				$timeSlot[0] = [$this->formatHour($hoursOfDay[0]), $this->formatHour($hoursOfDay[1])];
			}
			if ($endHour < 14 && !isset($timeSlot[1]))
			{
				$timeSlot[1] = null;
			}
		}
		if ($hoursOfDay[2] != '0000')
		{
			if (!isset($timeSlot[0]))
			{
				$timeSlot[0] = null;
			}
			$timeSlot[1] = [$this->formatHour($hoursOfDay[2]), $this->formatHour($hoursOfDay[3])];
		}
		return $timeSlot;
	}

	protected function formatHour($hour)
	{
		$h = substr($hour, 0, 2);
		$m = substr($hour, 2);
		return $h . ':' . $m;
	}

	/**
	 * @param \Change\Events\Event $event
	 * @return array
	 */
	public function onGetCityAutoCompletion($event)
	{
		$cities = $event->getParam('cities');
		if (is_array($cities))
		{
			return;
		}

		$context = $event->getParam('context') + ['data' => ['beginOfName' => null, 'countryCode' => null, 'options' => []]];
		$data = $context['data'];

		if (isset($data['options']['modeId']) && is_numeric($data['options']['modeId']))
		{
			$mode = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($data['options']['modeId']);
			if ($mode instanceof \Rbs\Mondialrelay\Documents\Mode)
			{
				$cities = [];
				$clientOptions = array('encoding' => 'utf-8', 'trace' => true);
				$soapClient = new \SoapClient($mode->getWSUrl(), $clientOptions);

				$params = array(
					'Enseigne' => $mode->getVendorcode(),
					'Pays' => $data['countryCode'],
					'Ville' => $data['beginOfName'],
					'CP' => "",
					'NbResult' => 10
				);
				$params["Security"] = $this->generateSecurityKey($params, $mode->getVendorprivatekey());

				$resultSoap = null;
				try
				{
					$resultSoap = $soapClient->WSI2_RechercheCP($params);
					if ($resultSoap != null)
					{
						$result = $resultSoap->WSI2_RechercheCPResult;
						if ($result->STAT == '0')
						{
							if (isset($result->Liste) && isset($result->Liste->Commune))
							{
								$commune = $result->Liste->Commune;
								if (is_array($commune))
								{
									foreach ($commune as $item)
									{
										$cities[] = ['title' => $item->Ville, 'zipCode' => $item->CP];
									}
								}
								elseif (is_object($commune))
								{
									$cities[] = ['title' => $commune->Ville, 'zipCode' => $commune->CP];
								}
							}
						}
					}
				}
				catch (\Exception $e)
				{
					$event->getApplication()->getLogging()->error('SOAP CALL Fail : ' . $e->getMessage());
				}
				$event->setParam('cities', $cities);
			}
		}
	}

	/**
	 * @param array $params
	 * @param string $privateKey
	 * @return string
	 */
	protected function generateSecurityKey($params, $privateKey)
	{
		$code = implode("", $params);
		$code .= $privateKey;
		return strtoupper(md5($code));
	}
}