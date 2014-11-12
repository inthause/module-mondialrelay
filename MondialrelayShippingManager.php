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
	 * @param \Change\Events\Event $event
	 * @return array
	 */
	public function getPoints($event)
	{
		$genericServices = $event->getServices('genericServices');
		if (!($genericServices instanceof \Rbs\Generic\GenericServices))
		{
			throw new \RuntimeException('Unable to get GenericServices', 999999);
		}

		$points = $event->getParam('points', []);
		$context = $event->getParam('context') + ['address' => [], 'position' => [], 'options' => []];
		if ($context['options']['modeId'] && count($points) == 0)
		{
			$documentManager = $event->getApplicationServices()->getDocumentManager();
			$mode = $documentManager->getDocumentInstance($context['options']['modeId']);
			if ($mode instanceof \Rbs\Mondialrelay\Documents\Mode)
			{

				$clientOptions = array('encoding' => 'utf-8', 'trace' => true);
				$soapClient = new \SoapClient($mode->getWSUrl(), $clientOptions);

				$params = array(
					'Enseigne' => $mode->getVendorcode(),
					'Pays' => isset($context['address']['country']) ? $context['address']['country'] : "FR",
					'Ville' => isset($context['address']['city']) ? $context['address']['city'] : "",
					'CP' => isset($context['address']['zipCode']) ? $context['address']['zipCode'] : "",
					'Latitude' => isset($context['position']['latitude']) ? $context['position']['latitude'] : "",
					'Longitude' => isset($context['position']['longitude']) ? $context['position']['longitude'] : "",
					'Taille' => "",
					'Poids' => isset($context['options']['weight']) ? $context['options']['weight'] : "",
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

								$point = new \Rbs\Geo\Map\Point();
								$point->setTitle(trim($item->LgAdr1));
								$point->setCode($item->Num);
								$point->setLatitude(floatval(str_replace(',', '.', $item->Latitude)));
								$point->setLongitude(floatval(str_replace(',', '.', $item->Longitude)));

								$letter = chr(ord('A') + $index);

								$country = $this->getCountryByCode($documentManager, $item->Pays);

								$address = new \Rbs\Geo\Address\BaseAddress(
									[\Rbs\Geo\Address\AddressInterface::COUNTRY_CODE_FIELD_NAME => $item->Pays,
									\Rbs\Geo\Address\AddressInterface::ZIP_CODE_FIELD_NAME => $item->CP,
									\Rbs\Geo\Address\AddressInterface::LOCALITY_FIELD_NAME => trim($item->Ville),
									'__addressFieldsId' => $country->getAddressFieldsId()
									]
								);

								$geoManager = $genericServices->getGeoManager();
								$address->setLines(array_merge([trim($item->LgAdr1), trim($item->LgAdr2),
									trim($item->LgAdr3), trim($item->LgAdr4)], $geoManager->getFormattedAddress($address)));
								$point->setAddress($address);

								$options = [
									'letter' => $letter,
									'iconUrl' => '/Theme/Rbs/Base/Rbs_Mondialrelay/img/pr' . $letter . '.png',
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

								// TODO Try to display information of disponibility
								// $item->Informations_Dispo
								// $item->Debut
								// $item->Fin
							}
						}
					}
				}
				catch (\Exception $e)
				{
					$event->getApplication()->getLogging()->error('SOAP CALL Fail : ' . $e->getMessage());
				}
			}
		}

		$event->setParam('points', $points);
	}

	/**
	 * @param \Change\Documents\DocumentManager $documentManager
	 * @param string $countryCode
	 * @return \Rbs\Geo\Documents\Country|null
	 */
	protected function getCountryByCode($documentManager, $countryCode)
	{
		$query = $documentManager->getNewQuery('Rbs_Geo_Country');
		$query->andPredicates($query->eq('code', $countryCode));
		return $query->getFirstDocument();
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
	public function getCityAutocompletion($event)
	{
		$cities = $event->getParam('cities', []);
		$context = $event->getParam('context');
		if ($context['options']['modeId'] && count($cities) == 0)
		{
			$mode = $event->getApplicationServices()->getDocumentManager()->getDocumentInstance($context['options']['modeId']);
			if ($mode instanceof \Rbs\Mondialrelay\Documents\Mode)
			{
				$clientOptions = array('encoding' => 'utf-8', 'trace' => true);
				$soapClient = new \SoapClient($mode->getWSUrl(), $clientOptions);

				$params = array(
					'Enseigne' => $mode->getVendorcode(),
					'Pays' => $context['countryCode'],
					'Ville' => $context['beginOfName'],
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
							if (isset($result->Liste))
							{
								if (isset($result->Liste->Commune))
								{
									foreach ($result->Liste->Commune as $item)
									{
										$cities[] = ['title' => $item->Ville, 'zipCode' => $item->CP];
									}
								}
							}
						}
					}
				}
				catch (\Exception $e)
				{
					$event->getApplication()->getLogging()->error('SOAP CALL Fail : ' . $e->getMessage());
				}
			}
		}

		$event->setParam('cities', $cities);
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