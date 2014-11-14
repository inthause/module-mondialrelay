(function ()
{
	"use strict";
	var app = angular.module('RbsChangeApp');

	document.write('<script type="text/javascript" src="http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js"></script>');
	// TODO Find the way to configure layers
	// document.write('<script type="text/javascript" src="http://maps.google.com/maps/api/js?v=3.2&sensor=false"></script>');
	document.write('<script type="text/javascript" src="http://matchingnotes.com/javascripts/leaflet-google.js"></script>');

	var head  = document.getElementsByTagName('head')[0];
	var link  = document.createElement('link');
	link.rel  = 'stylesheet';
	link.type = 'text/css';
	link.href = 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css';
	link.media = 'all';
	head.appendChild(link);

	function rbsCommerceShippingModeConfigurationMondialrelay($http, $compile)
	{
		return {
			restrict: 'AE',
			templateUrl: 'Theme/Rbs/Base/Rbs_Mondialrelay/shipping-mondialrelay.twig',
			scope: {
				'delivery': '='
			},

			link: function (scope, element, attributes)
			{
				scope.selectedIndex = null;
				scope.options = {modeId: scope.delivery.modeId};
				scope.data = [];
				scope.loading = false;
				scope.defaultLatitude = 48.856578;
				scope.defaultLongitude = 2.351828;
				scope.defaultCountry = 'FR';
				scope.defaultZipcode = '';
				scope.defaultZoom = 11;
				scope.markers = [];
				scope.bounds = [];
				scope.listDiv = element.find('#mondialRelayList');

				// TODO Find the way to configure layers
				// scope.layersToLoad = [{title: 'Google', code: 'GOOGLE'}, {title: 'OpenStreetMap', code: 'OSM'}];
				scope.layersToLoad = [{title: 'OpenStreetMap', code: 'OSM'}];

				scope.currentAddress = {country: scope.defaultCountry, zipCode: scope.defaultZipcode};
				scope.currentPosition = {latitude:null, longitude:null};

				// TODO HOW FIND IT ?
				scope.countries = [{title: 'France', code: 'FR'}, {title: 'Belgique', code: 'BE'}, {title: 'Luxembourg', code: 'LU'}, {title: 'Espagne', code: 'ES'}]

				scope.loadMap = function loadMap() {
					scope.map = L.map('ShippingMap', {
						center: [scope.defaultLatitude, scope.defaultLongitude],
						zoom: scope.defaultZoom
					});
					scope.loadLayers();
				};

				scope.loadLayers = function loadLayers() {
					var layers = {};
					var nbLayers = 0;
					for (var i=0; i<scope.layersToLoad.length;  i++){
						var l = null;
						if (scope.layersToLoad[i].code == 'GOOGLE')
						{
							// Possible types: SATELLITE, ROADMAP, HYBRID
							l = new L.Google('ROADMAP');
						}
						if (scope.layersToLoad[i].code == 'OSM')
						{
							l = new L.TileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
						}
						if (l != null)
						{
							layers[scope.layersToLoad[i]['title']] = l;
							scope.map.addLayer(l);
							nbLayers++;
						}
					}
					if (nbLayers > 1)
					{
						scope.map.addControl(new L.Control.Layers( layers, {}));
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

				scope.launchSearch = function launchSearch() {
					scope.setDeliveryInformation(null);
					scope.loading = true;
					$http.post('Action/Rbs/Geo/GetPoints',
						{address:scope.currentAddress, position:scope.currentPosition, options:scope.options})
						.success(function(data) {
							scope.data = data;
							scope.updateMarkers();
							scope.loading = false;
						})
						.error(function(data, status, headers) {
							console.log('error');
						});
				};

				scope.updateMarkers = function updateMarkers() {
					var i, latCenter = 0, longCenter = 0, dataLength;

					scope.removeMarkers();
					dataLength = scope.data.length;

					for(i=0; i<dataLength; i++)
					{
						latCenter = latCenter + scope.data[i].latitude;
						longCenter = longCenter + scope.data[i].longitude;

						scope.drawMarkerToMap(scope.data[i], i);
					}

					if (dataLength > 0)
					{
						scope.map.fitBounds(scope.bounds);
					}
				};

				scope.removeMarkers = function removeMarkers() {
					for(var i=0; i<scope.markers.length; i++)
					{
						scope.map.removeLayer(scope.markers[i]);
					}
					scope.markers = [];
					scope.bounds = [];
				};

				scope.drawMarkerToMap = function drawMarkerToMap(relay, index) {
					var relayIcon;
					if (relay.options.iconUrl)
					{
						relayIcon = L.icon({
							iconUrl: relay.options.iconUrl,
							iconSize: [30, 36]
						});
					}
					else
					{
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
						var html = '<div data-rbs-commerce-shipping-mode-configuration-popin-detail-mondialrelay="" data-relay="currentOpennedRelay"></div>';
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
					if (index == null)
					{
						scope.selectedIndex = null;
						scope.delivery.address = null;
						scope.delivery.isConfigured = false;
						scope.delivery.options.relay = null;
					}
					else
					{
						scope.selectedIndex = index;
						scope.delivery.address = angular.copy(scope.data[index].address);
						scope.delivery.isConfigured = true;
						scope.delivery.options.relay = scope.data[index];
					}
				};

				// Autocomplete city
				scope.getLocation = function getLocation(val) {
					return $http.post('Action/Rbs/Geo/GetCityAutocompletion',
						{beginOfName: val, countryCode: scope.currentAddress.country, options:scope.options})
						.then(function(res) {
							return res.data;
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

				// Init display
				scope.loadMap();
			}
		}
	}

	rbsCommerceShippingModeConfigurationMondialrelay.inject = ['$http', '$compile'];
	app.directive('rbsCommerceShippingModeConfigurationMondialrelay', rbsCommerceShippingModeConfigurationMondialrelay);

	function rbsCommerceShippingModeConfigurationMondialrelayReadonly()
	{
		return {
			restrict: 'AE',
			templateUrl: 'Theme/Rbs/Base/Rbs_Mondialrelay/shipping-mondialrelay-readonly.twig',
			scope: {
				'delivery': '='
			},

			link: function (scope, element, attributes)
			{
				scope.relay = scope.delivery.options.relay;
			}
		}
	}

	app.directive('rbsCommerceShippingModeConfigurationMondialrelayReadonly', rbsCommerceShippingModeConfigurationMondialrelayReadonly);

	function rbsCommerceShippingModeConfigurationPopinDetailMondialrelay() {
		return {
			restrict: 'AE',
			templateUrl: '/popinDetail.tpl',
			replace:true,
			scope: {
				relay : '='
			},

			link: function (scope, element, attributes)
			{
				scope.distance = null;
				scope.distanceUnit = 'm';
				if (scope.relay.options.distance)
				{
					var d = parseInt(scope.relay.options.distance);
					if (d >= 1000)
					{
						scope.distance = Math.round(d / 10)/100;
						scope.distanceUnit = 'km';
					}
					else
					{
						scope.distance = d;
					}
				}

			}
		}
	}
	app.directive('rbsCommerceShippingModeConfigurationPopinDetailMondialrelay', rbsCommerceShippingModeConfigurationPopinDetailMondialrelay);

})();