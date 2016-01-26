(function ($, Drupal, drupalSettings) {

  "use strict";

  Drupal.behaviors.bingMapsApi = {
    attach: function(context) {
      var map, $mapElement, infobox, pushPin;
      $('#bing-map').once('bing-map-attach').each(function () {
        var mm = Microsoft.Maps,
          settings = drupalSettings.bingMapsApi,
          $container = $(this).closest('.map-container'),
          $pin = $container.find('.pinbox img'), // Draggable pin.
          searchBox,
          center = new mm.Location(
            settings.center.latitude || 0.0,
            settings.center.longitude || 0.0
          );

        $mapElement = $(this);
        map = new mm.Map(this, {
          credentials: settings.key || '',
          center: center,
          mapTypeId: mm.MapTypeId.auto,
          showDashboard: true,
          showScalebar: true,
          zoom: settings.zoom || 7,
          width: $mapElement.width(),
          height: $mapElement.height()
        });

        /**
         * Update the location information.
         *
         * @param {Microsoft.Maps.Location} location
         */
        function updateLocation(location) {
          var latLng = location.latitude + "," + location.longitude;
          $.ajax({
            url: 'http://dev.virtualearth.net/REST/v1/Locations/' +
            latLng +
            "?output=json&key=" + settings.key,
            dataType: 'jsonp',
            jsonp: 'jsonp',
            success: function (data) {
              // Grab the first item (if present).
              var resourceSets = data.resourceSets,
                resources = resourceSets.length && resourceSets[0].resources,
                name = resources.length && resources[0].name,
                box;

              if (name) {
                // Show the locations listing if needed, and scroll to its top.
                $('.bing-location-results').removeClass('visually-hidden')
                box = $('.bing-location-results-dynamic').removeClass('visually-hidden');
                // Fill in the values.
                box.find('.name').val(name);
                box.find('span.address').text(name).attr({
                  'data-lat': location.latitude,
                  'data-lng': location.longitude
                }).click(showOnMap).parent('.form-item').addClass('address');
                box.find('input[id$=latlong]').val(latLng);
              }
            }
          });
        }

        /**
         * Shows a location on the map.
         *
         * The location data comes from the specified list item's data.
         *
         * @param {Event} event
         *   jQuery normalized Event object.
         * @param {Boolean} [pinOnly]
         *   Show only a pin instead of the location info.
         */
        function showOnMap(event, pinOnly) {
          var mm,
            location,
            $element = $(this),
            $active_list,
            options,
            $list_item = $element.closest('ul').find('li');

          if (map) {
            $list_item.removeClass('active');
            $active_list = $element.closest('li').addClass('active');
            mm = Microsoft.Maps;
            location = new mm.Location(parseFloat($element.attr('data-lat')), parseFloat($element.attr('data-lng')));
            map.setView({center: location, zoom: Math.max(15, map.getZoom())});

            if (pinOnly) {
              // Show only a pin on the location.
              if (pushPin) {
                pushPin.setLocation(location);
              }
              else {
                pushPin = new mm.Pushpin(location, {draggable: true});
                // The new pin on the map can also be dragged around.
                mm.Events.addHandler(pushPin, 'dragend', function (target) {
                  updateLocation(target.entity.getLocation());
                });
                map.entities.push(pushPin);
              }
            }
            else {
              options = {
                title: $active_list.find('.name span').text() || $active_list.find('.name').val(),
                description: $active_list.find('.address span').text(),
                height: 100,
                width: 250,
                visible: true
              };

              if (!infobox) {
                infobox = new mm.Infobox(location, options);
                map.entities.push(infobox);
              }
              else {
                infobox.setOptions(options);
                infobox.setLocation(location);
              }
              $(window).scrollTop($mapElement.offset().top);
            }

          }
        }

        /**
         * Handle the case when the pin is dropped onto the map.
         *
         * @param {Event} event
         * @param ui jQuery UI event data
         * @param ui.offset The offset of the dropped element
         * @param ui.position Relative position of the dropped element to the target
         * @param ui.helper Helper object that is dropped.
         * @param ui.draggable The draggable object whose helper is dropped.
         */
        function dropOnMap(event, ui) {
          var item = ui.helper,
            offset = ui.offset,
            mapOffset = $mapElement.offset(),
          // Get the pin point relative to the map's offset and the point's offset.
          // Need to adjust it relative to the center of the map.
            location = map.tryPixelToLocation(new mm.Point(
              item.width() / 2 + offset.left - mapOffset.left - map.getWidth() / 2,
              item.height() + offset.top - mapOffset.top - map.getHeight() / 2
            ));
          if (location) {
            if (pushPin) {
              pushPin.setLocation(location);
            }
            else {
              pushPin = new mm.Pushpin(location, {draggable: true});
              // The new pin on the map can also be dragged around.
              mm.Events.addHandler(pushPin, 'dragend', function (target) {
                updateLocation(target.entity.getLocation());
              });
              map.entities.push(pushPin);
            }

            map.setView({center: location, zoom: Math.max(15, map.getZoom())});
            updateLocation(location);
          }
        }

        if ($pin.length) {
          // There is a pin to be dragged to the map, prepare it.
          $pin.draggable({
            revert: true,
            zIndex: 2700
          });

          // Pin can be dropped on the map.
          $mapElement.droppable({
            accept: function (item) {
              // Only the pin can be dropped onto the map.
              return item[0] === $pin[0];
            },
            drop: dropOnMap
          });
        }
        else {
          // Just push a pin on the center.
          pushPin = new mm.Pushpin(center);
          map.entities.push(pushPin);
        }

        $('.bing-location-results').once('location-results', function () {
          var current = $('.location-current'), // Current location.
            items = $(this).add(current).find('.name span[data-lat][data-lng]'),
            item = items.length === 1 ? items[0] :
              current.length && items.length === 2 ? items[1] : null;

          items.click(showOnMap).addClass('result-name');

          if (item) {
            // There is only one search result, or there is a the current location.
            showOnMap.call(item, null, true);
          }
        });
      });

      // Prevent form submit on pressing enter key.
      var bingSearchButton = $('.button[data-drupal-selector*="bing-location"][data-drupal-selector*="action-search"]');
      var bingSearchField = $('input[data-drupal-selector*="bing-location"][data-drupal-selector*="user-input"]');
      bingSearchField.on('keydown', function (e) {
        if (e.which === 13) {
          e.preventDefault();
          bingSearchButton.mousedown();
        }
      });

    }
  };

})(jQuery, Drupal, drupalSettings);
