(function () {
	"use strict";
	var app = angular.module('RbsChangeApp');
	window.__change.__resources.push(function() {window.__change.RBS_Geo.initLeafletMap()});

	function rbsMondialrelayModeEditor(AjaxAPI, $compile) {
		var baseTemplateURL = null;

		function getBaseTemplateURL() {
			if (baseTemplateURL === null) {
				var navigationContext = AjaxAPI.globalVar('navigationContext');
				var themeName = (angular.isObject(navigationContext) ? navigationContext.themeName : null) || 'Rbs_Base';
				baseTemplateURL = 'Theme/' + themeName.split('_').join('/');
			}
			return baseTemplateURL;
		}

		function templateEditorURL() {
			return getBaseTemplateURL() + '/Rbs_Mondialrelay/shipping.twig';
		}

		return {
			restrict: 'A',
			templateUrl: templateEditorURL,
			require: '^rbsCommerceProcess',
			scope: {
				shippingMode: '=',
				shippingModeInfo: '=',
				userAddresses: '='
			},
			link: function (scope, element, attributes, processController) {
				scope.selectedIndex = null; // Selected point index.
				scope.options = {modeId: scope.shippingModeInfo.common.id};
				scope.data = [];
				scope.loading = false;
				scope.defaultLatitude = null;
				scope.defaultLongitude = null;
				scope.defaultZoom = null;
				scope.markers = [];
				scope.bounds = [];
				scope.listDiv = element.find('#mondialRelayList');
				scope.titleLayer = null;
				scope.currentAddress = {country: '', zipCode: ''};
				scope.currentPosition = {latitude:null, longitude:null};
				scope.relayAddress = null;

				scope.countries = [];

				scope.$watch('shippingMode.shippingZone', function(zoneCode) {
					AjaxAPI.getData('Rbs/Geo/AddressFieldsCountries/', {zoneCode: zoneCode})
						.success(function(data) {
							scope.countries = data.items;
							if (scope.countries.length == 1) {
								scope.currentAddress.country = scope.countries[0].common.code;
							}
						})
						.error(function(data, status, headers) {
							console.log('addressFieldsCountries error', data, status, headers);
							scope.countries = [];
							scope.currentAddress.country = '';
						});
				});

				scope.setTaxZone = function(taxZone) {
					if (processController.getObjectData().common.zone != taxZone) {
						var actions = {
							setZone: {zone : taxZone}
						};
						processController.updateObjectData(actions);
					}
				};

				scope.loadMap = function() {
					scope.map = L.map('ShippingMap-' + scope.shippingModeInfo.common.id, {
						center: [scope.defaultLatitude, scope.defaultLongitude],
						zoom: scope.defaultZoom
					});
					scope.loadLayers();
				};

				scope.loadLayers = function loadLayers() {
					var l = null;
					if (scope.titleLayer == 'GOOGLE') {
						l = new L.Google('ROADMAP');
					} else if (scope.titleLayer == 'OSM') {
						l = new L.TileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
					}
					if (l != null) {
						scope.map.addLayer(l);
					}
				};

				scope.locateMe = function locateMe() {
					navigator.geolocation.getCurrentPosition(
						function (position) {
							scope.map.setView([position.coords.latitude, position.coords.longitude]);
							scope.currentPosition.latitude = position.coords.latitude;
							scope.currentPosition.longitude = position.coords.longitude;
							scope.currentAddress.city = '';
							scope.currentAddress.zipCode = '';
							scope.launchSearch();
						},
						function (error) {
							alert("Localisation failed : [" + error.code + "] " + error.message);
						},
						{
							timeout: 5000,
							maximumAge: 0
						}
					);
				};

				scope.searchWithAddress = function searchWithAddress() {
					scope.currentPosition = {latitude:null, longitude:null};
					scope.launchSearch();
				};

				scope.launchSearch = function() {
					scope.setDeliveryInformation(null);
					scope.loading = true;

					AjaxAPI.getData('Rbs/Geo/Points/',
						{address: scope.currentAddress, position:scope.currentPosition,
							options: scope.options,
							matchingZone: scope.shippingMode.shippingZone || scope.shippingMode.taxesZones})
						.success(function(data) {
							scope.data = data.items;
							scope.updateMarkers();
							scope.loading = false;
						})
						.error(function(data, status, headers) {
							console.log('launchSearch error', data, status);
							scope.data = [];
							scope.loading = false;
						});
				};

				scope.updateMarkers = function() {
					var i,dataLength;

					scope.removeMarkers();
					dataLength = scope.data.length;
					if (!dataLength) {
						return;
					}

					var prCode = scope.preSelectedRelay ? scope.preSelectedRelay.code : null, selectedIndex = false;

					for (i = 0; i < dataLength; i++) {
						scope.drawMarkerToMap(scope.data[i], i);
						if (scope.data[i].code == prCode) {
							selectedIndex = i;
						}
					}

					scope.map.fitBounds(scope.bounds);

					if (selectedIndex !== false) {
						scope.selectRelay(selectedIndex);
					}
				};

				scope.removeMarkers = function() {
					for(var i=0; i<scope.markers.length; i++) {
						scope.map.removeLayer(scope.markers[i]);
					}
					scope.markers = [];
					scope.bounds = [];
				};

				scope.drawMarkerToMap = function(relay, index) {
					var relayIcon;
					if (relay.options.iconUrl) {
						relayIcon = L.icon({
							iconUrl: relay.options.iconUrl,
							iconSize: [30, 36]
						});
					} else {
						relayIcon = L.icon({
							iconSize: [30, 36]
						});
					}

					var marker = L.marker([relay.latitude, relay.longitude], {icon: relayIcon}).addTo(scope.map);
					marker.bindPopup('<div class="marker-popup-content"></div>', {minWidth:220, offset:L.point(0, -10)});

					marker.on('popupopen', function(e){
						scope.map.setView([relay.latitude, relay.longitude]);
						scope.currentOpennedRelay = relay;
						var popupContent = element.find('.marker-popup-content');
						var html = '<div data-rbs-mondialrelay-popin-detail="" data-relay="currentOpennedRelay"></div>';
						$compile(html)(scope, function (clone) {
							popupContent.append(clone);
						});

						var mapBounds = scope.map.getBounds();
						scope.map.setView([relay.latitude + (4*(Math.abs((relay.latitude - mapBounds._southWest.lat))/5)), relay.longitude]);
					});

					marker.on('popupclose', function(e){
						scope.currentOpennedRelay = null;
						var popupContent = element.find('.marker-popup-content');
						var collection = popupContent.children();
						collection.each(function() {
							var isolateScope = angular.element(this).isolateScope();
							if (isolateScope) {
								isolateScope.$destroy();
							}
						});
						collection.remove();
					});
					marker.on('click', function(e){
						scope.selectedIndex = index;
						scope.setDeliveryInformation(index);

						var scrollTo = element.find('#point'+index);
						if (scrollTo && scope.listDiv)
						{
							scope.listDiv.animate({
								scrollTop: scrollTo.offset().top - scope.listDiv.offset().top + scope.listDiv.scrollTop()
							}, 1000);
						}
					});

					scope.markers.push(marker);
					scope.bounds.push(L.latLng(relay.latitude, relay.longitude));
				};

				scope.selectRelay = function selectRelay(index){
					scope.setDeliveryInformation(index);
					scope.markers[index].openPopup();
				};

				scope.setDeliveryInformation = function setDeliveryInformation(index) {
					scope.shippingMode.valid = relayValid;
					if (index == null) {
						scope.selectedIndex = null;
						scope.relayAddress = null;
						scope.shippingMode.options.relay = null;
					} else {
						scope.selectedIndex = index;
						scope.relayAddress = scope.data[index].address;
						var taxZone = scope.data[index].options.matchingZone;
						if (taxZone && taxZone !== true && scope.shippingMode.taxesZones) {
							scope.setTaxZone(taxZone);
						}
						scope.shippingMode.options.relay = scope.data[index];
					}
				};

				// Auto-complete city.
				scope.getLocation = function(val) {
					return AjaxAPI.getData('Rbs/Geo/CityAutoCompletion/',
						{beginOfName: val, countryCode: scope.currentAddress.country, options:scope.options})
						.then(function(res) {
							if (res.status == 200) {
								return res.data.items;
							}
							return [];
						});
				};

				scope.updateZipCode = function updateZipCode(item, model, label) {
					if (item != null && item.zipCode != null)
					{
						scope.currentAddress.zipCode = item.zipCode;
						scope.launchSearch();
					}
				};

				scope.$watch('currentAddress.country', function(){
					scope.currentAddress.city = '';
					scope.currentAddress.zipCode = '';
					scope.data = [];
					scope.removeMarkers();
				});

				scope.$watch('shippingModeInfo', function(shippingModeInfo) {
					if (shippingModeInfo) {
						// Init display
						scope.titleLayer = shippingModeInfo.editor.titleLayer;
						scope.defaultLatitude = shippingModeInfo.editor.defaultLatitude;
						scope.defaultLongitude = shippingModeInfo.editor.defaultLongitude;
						scope.defaultZoom = shippingModeInfo.editor.defaultZoom;
						scope.preSelectedRelay = null;

						var launchSearch = false;
						if (scope.shippingMode.id == shippingModeInfo.common.id)  {
							scope.shippingMode.valid = relayValid;
							if (scope.shippingMode.options && scope.shippingMode.options.relay) {
								var relay = scope.shippingMode.options.relay;
								if (relay.searchAtPosition) {
									scope.preSelectedRelay = relay;
									scope.currentPosition = relay.searchAtPosition;
									if (relay.searchAtPosition.latitude) {
										scope.defaultLatitude = relay.searchAtPosition.latitude;
										scope.defaultLongitude = relay.searchAtPosition.longitude;
										launchSearch = true;
									}
								}
								if (relay.searchAtAddress) {
									scope.preSelectedRelay = relay;
									scope.currentAddress = relay.searchAtAddress;
									if (scope.currentAddress.country && scope.currentAddress.zipCode) {
										launchSearch = true;
									}
								}
							}
						}
						scope.loadMap();
						if (launchSearch) {
							scope.launchSearch();
						}
					}
				});

				function relayValid(returnData) {
					if (returnData) {
						var shippingMode = scope.shippingMode;
						var relay = angular.copy(shippingMode.options.relay);
						delete relay.address;
						relay.searchAtAddress = scope.currentAddress;
						relay.searchAtPosition = scope.currentPosition;
						return {
							id: scope.shippingModeInfo.common.id, title: scope.shippingModeInfo.common.title,
							lineKeys: shippingMode.lineKeys,
							address: scope.relayAddress,
							options: { category: 'relay', relay: relay }
						};
					}
					return (scope.shippingModeInfo.common.id == scope.shippingMode.id) && scope.relayAddress != null;
				}

				scope.$watch('shippingMode.id', function(id) {
					if (id == scope.shippingModeInfo.common.id) {
						scope.map.invalidateSize(false);
						scope.shippingMode.valid = relayValid;
					}
				});
			}
		}
	}
	rbsMondialrelayModeEditor.$inject = ['RbsChange.AjaxAPI', '$compile'];
	app.directive('rbsMondialrelayModeEditor', rbsMondialrelayModeEditor);

	function rbsMondialrelayModeSummary(AjaxAPI) {

		function templateSummaryURL() {
			var navigationContext = AjaxAPI.globalVar('navigationContext');
			var themeName = (angular.isObject(navigationContext) ? navigationContext.themeName : null) || 'Rbs_Base';
			return 'Theme/' + themeName.split('_').join('/') + '/Rbs_Mondialrelay/shipping-readonly.twig';
		}

		return {
			restrict: 'A',
			templateUrl: templateSummaryURL,
			scope: {
				'shippingMode': '='
			},

			link: function (scope, element, attributes)
			{
				scope.relay = scope.shippingMode.options.relay;
			}
		}
	}
	rbsMondialrelayModeSummary.$inject = ['RbsChange.AjaxAPI'];
	app.directive('rbsMondialrelayModeSummary', rbsMondialrelayModeSummary);

	function rbsMondialrelayPopinDetail(AjaxAPI) {

		function templatePopinDetailURL() {
			var navigationContext = AjaxAPI.globalVar('navigationContext');
			var themeName = (angular.isObject(navigationContext) ? navigationContext.themeName : null) || 'Rbs_Base';
			return 'Theme/' + themeName.split('_').join('/') + '/Rbs_Mondialrelay/popin-detail.twig';
		}

		return {
			restrict: 'A',
			templateUrl: templatePopinDetailURL,
			replace:true,
			scope: {
				relay : '='
			},
			link: function (scope, element, attributes) {
				scope.distance = null;
				scope.distanceUnit = 'm';
				if (scope.relay.options.distance) {
					var d = parseInt(scope.relay.options.distance);
					if (d >= 1000) {
						scope.distance = Math.round(d / 10)/100;
						scope.distanceUnit = 'km';
					} else {
						scope.distance = d;
					}
				}
			}
		}
	}
	rbsMondialrelayPopinDetail.$inject = ['RbsChange.AjaxAPI'];
	app.directive('rbsMondialrelayPopinDetail', rbsMondialrelayPopinDetail);

	app.run(["$templateCache", function($templateCache) {
		$templateCache.put("/Rbs_Mondialrelay/typeahead-city-template.tpl",
			'<a class="text-left">(= match.model.title =) ((= match.model.zipCode =))</a>');
	}]);
})();