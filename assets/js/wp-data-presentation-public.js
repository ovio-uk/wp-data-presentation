( function ( $ ) {
  'use strict';

    var self = {};
    var global_markers = [];
    var selectedLocations = [];
    var selectedIncidentsGraphs = [];
    var selectedFatGraphs = [];
    var selectedIncidents = [];
    var selectedActorNames = [];
    var selectedActors = [];
    var selectedFat = [];
    var fromYear = '';
    var toYear = '';
    var timeframe = '';
    var targetCiv = '';
    const interLabels = {
      1: "State Forces",
      2: "Rebel Groups",
      3: "Political Militias",
      4: "Identity Militias",
      5: "Rioters",
      6: "Protesters",
      7: "Civilians",
      8: "External/Other Force"
    };

    self.init = function(){
      self.currentMapType = $('input[name="wpdp_search_location_country"]').length > 0 ? 
      ($('input[name="wpdp_search_location_country"]').val() == '' ? 'polygons' : 'points') : 'points';
      
      self.setDefaultFilters();
      self.filtersChange();
      self.menuFilters();
      self.graphChange();
      self.dataTables();
      self.expandable();
      self.showMapDetails();
      self.datePicker();
      self.checkbox();
      self.checkfForIndeterminate();
      self.select2();
      self.tippy();
    },

    self.tippy = function(){
      tippy('.tippy-icon', {
        trigger: 'click',
      });
    },

    self.setDefaultFilters = function(){
      selectedLocations = [];
      selectedIncidents = [];
      selectedActors = [];
      selectedActorNames = [];
      selectedFat = [];
      selectedIncidentsGraphs = [];
      selectedFatGraphs = [];
      fromYear = '';
      toYear = '';
      timeframe = '';
      targetCiv = '';

      fromYear = $("#wpdp_from").val();
      toYear = $("#wpdp_to").val();

      targetCiv = $('input[type="radio"][name="target_civ"]:checked').val();

      if(fromYear == '' && JSON.parse(wpdp_shortcode_atts).from != ''){
        fromYear = JSON.parse(wpdp_shortcode_atts).from;
      }

      if(toYear == '' && JSON.parse(wpdp_shortcode_atts).to != ''){
        toYear = JSON.parse(wpdp_shortcode_atts).to;
      }

      timeframe = $("#wpdp_date_timeframe").val();


      $('input[type="checkbox"].wpdp_location:checked').each(function() {
        var isChildChecked = $(this).closest('ul').parent().children('input[type="checkbox"].wpdp_location:checked').length > 0;

        if (!isChildChecked) {
          selectedLocations.push($(this).val());
        }
      });

      if($('#wpdp_search_location').val() && $('#wpdp_search_location').val().length > 0){
        selectedLocations = selectedLocations.concat($('#wpdp_search_location').val());
      }

      selectedIncidents = self.checkedSelector('wpdp_incident_type');

      selectedActors = self.checkedSelector('wpdp_actors');

      selectedActorNames = $('#wpdp_search_actors').val();

      $('input[type="checkbox"].wpdp_fat:checked').each(function() {
        selectedFat.push($(this).val());
      });

      $('input[type="checkbox"].wpdp_incident_type, input[type="checkbox"].wpdp_fat').removeClass('all_checked');

      // Add class all_checked to parent input checkbox if all direct children are selected
      $('input[type="checkbox"].wpdp_incident_type.level_1:checked, input[type="checkbox"].wpdp_fat.level_1:checked').each(function() {
        

        if($(this).parent().find('ul li input[type="checkbox"].wpdp_incident_type.level_2:checked').length === $(this).parent().find('ul li input[type="checkbox"].wpdp_incident_type.level_2').length){
          $(this).addClass('all_checked');
        }

        if($(this).parent().find('ul li input[type="checkbox"].wpdp_fat.level_2:checked').length === $(this).parent().find('ul li input[type="checkbox"].wpdp_fat.level_2').length){
          $(this).addClass('all_checked');
        }

      });

      $('input[type="checkbox"].wpdp_incident_type.level_2:checked, input[type="checkbox"].wpdp_fat.level_2:checked').each(function() {

        if($(this).parent().find('ul li input[type="checkbox"].wpdp_incident_type.level_3:checked').length === $(this).parent().find('ul li input[type="checkbox"].wpdp_incident_type.level_3').length){
          $(this).addClass('all_checked');
        }

        if($(this).parent().find('ul li input[type="checkbox"].wpdp_fat.level_3:checked').length === $(this).parent().find('ul li input[type="checkbox"].wpdp_fat.level_3').length){
          $(this).addClass('all_checked');
        }

      });


      // Use the new function to collect graph items
      const graphItems = self.collectGraphItems();
      selectedIncidentsGraphs = graphItems.incidents;
      selectedFatGraphs = graphItems.fatalities;

    },

    self.collectGraphItems = function() {
      let selectedIncidentsGraphs = [];
      let selectedFatGraphs = [];

      // Helper function to collect items from a specific level
      const collectFromLevel = function(level) {
        $('input[type="checkbox"].wpdp_incident_type.level_' + level + ':checked').each(function() {
          let val = $(this).val();
          if (val.includes('+')) {
            val.split('+').forEach(item => selectedIncidentsGraphs.push(item));
          } else {
            selectedIncidentsGraphs.push(val);
          }
          
        });

        $('input[type="checkbox"].wpdp_fat.level_' + level + ':checked').each(function() {
          let val = $(this).val();
          if (val.includes('+')) {
            val.split('+').forEach(item => selectedFatGraphs.push(item));
          } else {
            selectedFatGraphs.push(val);
          }
        });
      };


      // Helper function to remove duplicates between arrays
      const removeDuplicates = function(array1, array2) {
        const duplicates = array1.filter(item => array2.includes(item));
        
        // Remove duplicates from array2, keeping them in array1
        duplicates.forEach(item => {
          const index = array2.indexOf(item);
          if (index > -1) {
            array2.splice(index, 1);
          }
        });

        return {
          array1: [...new Set(array1)], // Remove any internal duplicates
          array2: [...new Set(array2)]  // Remove any internal duplicates
        };
      };

      // Collect items level by level until we have enough unique items
      for (let level = 1; level <= 3; level++) {
        collectFromLevel(level);

        // Remove duplicates after each collection
        const cleaned = removeDuplicates(selectedIncidentsGraphs, selectedFatGraphs);
        selectedIncidentsGraphs = cleaned.array1;
        selectedFatGraphs = cleaned.array2;

      }

      return {
        incidents: selectedIncidentsGraphs,
        fatalities: selectedFatGraphs
      };
    };


    self.checkedSelector = function(className){
      var selected = [];
      $('input[type="checkbox"].' + className + ':checked').each(function() {
        selected.push($(this).val());
      });

      return selected;

    },

    self.select2 = function(){
      $('#wpdp_search_location').select2({
        multiple: true,
        ajax: {
          url: wpdp_obj.ajax_url,
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              search: params.term,
              action: 'search_location',
              country: $('input[name="wpdp_search_location_country"]').length ? $('input[name="wpdp_search_location_country"]').val() : ''
            };
          },
          processResults: function (data) {
            // Sort the results alphabetically by location
            data.sort(function(a, b) {
              var textA = a.location.toUpperCase();
              var textB = b.location.toUpperCase();
              return (textA < textB) ? -1 : (textA > textB) ? 1 : 0;
            });
            return {
              results: $.map(data, function(item) {
                return {
                  id: item.id,
                  text: item.location === item.country ? item.location : item.location + ' (' + item.country + ')'
                };
              })
            };
          },
          cache: true
        },
        minimumInputLength: 3,
        placeholder: 'Search location',
        width: 'element',
        dropdownParent: $('#wpdp_search_location').parent()
      });

      $('#wpdp_search_actors').select2({
        multiple: true,
        minimumInputLength: 3,
        ajax: {
          url: wpdp_obj.ajax_url,
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              search: params.term,
              action: 'search_actor_names'
            };
          },
          processResults: function (data) {
            return {
              results: $.map(data, function(item) {
                return {
                  id: item.id,
                  text: item.text
                };
              })
            };
          },
          cache: true
        },
        placeholder: 'Search Actor Name',
        width: 'element',
        dropdownParent: $('#wpdp_search_actors').parent()
      });

    },

    self.checkfForIndeterminate = function(){
      setTimeout(() => {
        $('ul.first_one li.expandable:has(ul li)').each(function() {
          var $parentCheckbox = $(this).children('input[type="checkbox"]');
          var $childCheckboxes = $(this).find('ul input[type="checkbox"]');
          
          var allChecked = $childCheckboxes.length > 0 && $childCheckboxes.filter(':checked').length === $childCheckboxes.length;
          var someChecked = $childCheckboxes.filter(':checked').length > 0;
  
          if (allChecked) {
              $parentCheckbox.prop('indeterminate', false);
              $parentCheckbox.prop('checked', true);
          } else if (someChecked) {
              $parentCheckbox.prop('indeterminate', true);
          } else {
              $parentCheckbox.prop('indeterminate', false);
          }
      });
  
      }, 100);
    }


    self.checkbox = function(){
      $('.filter_data li input[type="checkbox"]:not(.select_unselect_all)').on('change', function() {
        $('.filter_data .no_data').hide();
        self.checkfForIndeterminate();
      });

      $('.filter_data li input[type="checkbox"]').not('.wpdp_location, .select_unselect_all').on('change', function() {
        $(this).parent().find('li input[type="checkbox"]').prop('checked', this.checked).change();
      });

      // Reset - Updated version
      $('#filter_form').on('reset', function(e) {
        // Prevent the default reset behavior
        e.preventDefault();
        
        // Set default dates based on map existence
        var maxDate = new Date();
        var defaultFromDate = new Date();
        if (typeof google === 'object' && typeof google.maps === 'object') {
          // If maps exist, set from date to 1 month ago
          defaultFromDate.setMonth(defaultFromDate.getMonth() - 1);
        } else {
          // If no maps, set from date to 1 year ago
          defaultFromDate.setFullYear(defaultFromDate.getFullYear() - 1);
        }

        // Set the datepicker values
        $('#wpdp_from').datepicker('setDate', defaultFromDate).addClass('changed');
        $('#wpdp_to').datepicker('setDate', maxDate).addClass('changed');

        // Check all checkboxes 
        $(this).find('.incident_type input[type="checkbox"],.fatalities input[type="checkbox"],.actors input[type="checkbox"]').prop('checked', true);

        // Reset chart controls to default values which are level 1 and 2
        if($('.chart-controls').length){
          $('.chart-controls').each(function(){
            if(!$(this).find('.chart-filter-btn.top-level').hasClass('active')){
              $(this).find('.chart-filter-btn.top-level').trigger('click').addClass('active');
            }
            if(!$(this).find('.chart-filter-btn.second-level').hasClass('active')){
              $(this).find('.chart-filter-btn.second-level').trigger('click').addClass('active');
            }
            if(!$(this).find('.chart-filter-btn.all-categories').hasClass('active')){
              $(this).find('.chart-filter-btn.all-categories').trigger('click');
            }
          });
        }
        
        // Clear select2 fields
        $('#wpdp_search_location').val('').trigger('change');
        $('#wpdp_search_actors').val('').trigger('change');
        
        // Clear regular select fields
        $(this).find('select').val('').trigger('change');
        
        // Hide no data message
        $('.filter_data .no_data').hide();

        
        // Force recheck indeterminate states
        self.checkfForIndeterminate();

        // Trigger form change to update any dependent elements
        $(this).trigger('change');
      });

      // Select/Unselect All
      $(document).on('click', 'li a.select_unselect_all', function(e) {
        e.preventDefault();
        var $content = $(this).closest('.content');
        
        requestAnimationFrame(function() {
          var checkboxes;
          if ($content.closest('.grp').hasClass('locations')) {
            checkboxes = $content.find('li input[type="checkbox"]');
            if(!checkboxes.length){
              checkboxes = $content.find('.checkboxes_locations > ul > li > input[type="checkbox"]');
            }
          } else {
            checkboxes = $content.find('input[type="checkbox"]');
          }

          // Count total and checked checkboxes
          var totalChecked = checkboxes.filter(':checked').length;
          var shouldCheck = totalChecked < checkboxes.length;

          // Set all checkboxes to either checked or unchecked
          checkboxes.each(function(index, checkbox) {
            checkbox.checked = shouldCheck;
            checkbox.indeterminate = false;
          });
        });
      });

      // Selected country hidden field
      $(document).on('change', 'input[name="wpdp_country"]:radio', function() {
        let val = $(this).val().split('__')[0];
        
        $('input[name="wpdp_search_location_country"]').val(val);
      });

      // View countries button
      $('.view_countries').on('click', function() {
        $('input[name="wpdp_search_location_country"]').val('').parents('form').submit();
      });

    };


    self.datePicker = function(){

      var minDate = new Date(wpdp_filter_dates[0]); 
      var maxDate = new Date(wpdp_filter_dates[wpdp_filter_dates.length - 1]);

      $('#wpdp_from').datepicker({
        minDate: minDate,
        maxDate: maxDate,
        dateFormat: 'dd MM yy',
        defaultDate: minDate,
        showButtonPanel: true,
        closeText: 'Clear',
        changeMonth: true,
        changeYear: true,
        yearRange: "c-100:c+100",
        onSelect: function(selectedDate) {
          $('#wpdp_from').addClass('changed');
          $('.filter_data .no_data').hide();
        },
        beforeShow: function (input, inst) {
          setTimeout(function () {
              var clearButton = $(input)
                  .datepicker("widget")
                  .find(".ui-datepicker-close");
              clearButton.unbind("click").bind("click", function () {
                  $.datepicker._clearDate(input);
              });
          }, 1);
        }

      });
      
      $('#wpdp_to').datepicker({
        minDate: minDate,
        maxDate: maxDate,
        dateFormat: 'dd MM yy',
        defaultDate: maxDate,
        changeMonth: true,
        showButtonPanel: true,
        closeText: 'Clear',
        changeYear: true,
        yearRange: "c-100:c+100",
        onSelect: function(selectedDate) {
          $('#wpdp_to').addClass('changed');
          $('.filter_data .no_data').hide();
        },
        beforeShow: function (input, inst) {
          setTimeout(function () {
              var clearButton = $(input)
                  .datepicker("widget")
                  .find(".ui-datepicker-close");
              clearButton.unbind("click").bind("click", function () {
                  $.datepicker._clearDate(input);
              });
          }, 1);
        }

      });

    },


    self.showMapDetails = function(){
      $(document).on('click','.map_more_details span',function(e){
        e.preventDefault();
        let det = $(this).next().html();
        // Create modal elements
        let modal = $('<div>').addClass('wpdp_modal modal');
        let content = $('<div>').addClass('modal-content');
        let close = $('<button>').addClass('close-button').html('&times;');

        // Append elements
        content.append(close);
        content.append(det);
        modal.append(content);
        $('body').append(modal);

        // Show modal
        modal.show();

        // Hide modal when clicking on 'X' or outside modal
        close.click(function() {
            modal.hide();
        });

        $(window).click(function(event) {
            if ($(event.target)[0] == modal[0]) {
                modal.hide();
            }
        });
      });
    },

    self.expandable = function(){
      $(".wpdp .grp .title").click(function() {
        $(this).parent().toggleClass("active");
        $(this).find(".dashicons").toggleClass("dashicons-arrow-down-alt2 dashicons-arrow-up-alt2");
      });
    },

    self.maps = function(){

      var selectedCountry = $('input[name="wpdp_search_location_country"]').length ? $('input[name="wpdp_search_location_country"]').val() : '';

      $('#wpdp-loader').css('display','flex');
      self.setDefaultFilters();
      $.ajax({
        url: wpdp_obj.ajax_url,
        data: {
          action:'wpdp_map_request',
          type_val: selectedIncidents,
          locations_val: selectedLocations,
          actors_val: selectedActors,
          actors_names_val: selectedActorNames,
          fat_val: selectedFat,
          from_val: fromYear,
          to_val: toYear,
          target_civ: targetCiv,
          selected_country: selectedCountry
        },
        type: 'POST',
        success: function(response) {

          self.mapInit(response.data.data);
          $('#wpdp-loader').hide();

          // $('.wpdp .con').css('left','-152%').removeClass('active');
          // $('.wpdp .filter span').attr('class','fas fa-sliders-h');
          if(response.data.count == 0){
            $('.filter_data .no_data').show();
          }
        },
        error: function(errorThrown){
            alert('No data found');
        }
      });


    },


    self.getCenterLocation = function(mapData) {
      let totalLat = 0;
      let totalLng = 0;
      let count = mapData.length;
    
      mapData.forEach(function(loc) {
        totalLat += parseFloat(loc.latitude);
        totalLng += parseFloat(loc.longitude);
      });
      
      var lat = (totalLng / count);

      if($('.wpdp .filter').find('span').hasClass('fa-arrow-left')){
        lat = lat + 12;
      }

      return {
        lat: totalLat / count,
        lng: lat
      };
    },

    self.mapsStyles = function(){
      return [
        {
            "featureType": "water",
            "elementType": "geometry",
            "stylers": [
                {
                    "color": "#e9e9e9"
                },
                {
                    "lightness": 17
                }
            ]
        },
        {
            "featureType": "landscape",
            "elementType": "geometry",
            "stylers": [
                {
                    "color": "#f5f5f5"
                },
                {
                    "lightness": 20
                }
            ]
        },
        {
            "featureType": "road.highway",
            "elementType": "geometry.fill",
            "stylers": [
                {
                    "color": "#ffffff"
                },
                {
                    "lightness": 17
                }
            ]
        },
        {
            "featureType": "road.highway",
            "elementType": "geometry.stroke",
            "stylers": [
                {
                    "color": "#ffffff"
                },
                {
                    "lightness": 29
                },
                {
                    "weight": 0.2
                }
            ]
        },
        {
            "featureType": "road.arterial",
            "elementType": "geometry",
            "stylers": [
                {
                    "color": "#ffffff"
                },
                {
                    "lightness": 18
                }
            ]
        },
        {
            "featureType": "road.local",
            "elementType": "geometry",
            "stylers": [
                {
                    "color": "#ffffff"
                },
                {
                    "lightness": 16
                }
            ]
        },
        {
            "featureType": "poi",
            "elementType": "geometry",
            "stylers": [
                {
                    "color": "#f5f5f5"
                },
                {
                    "lightness": 21
                }
            ]
        },
        {
            "featureType": "poi.park",
            "elementType": "geometry",
            "stylers": [
                {
                    "color": "#dedede"
                },
                {
                    "lightness": 21
                }
            ]
        },
        {
            "elementType": "labels.text.stroke",
            "stylers": [
                {
                    "visibility": "on"
                },
                {
                    "color": "#ffffff"
                },
                {
                    "lightness": 16
                }
            ]
        },
        {
            "elementType": "labels.text.fill",
            "stylers": [
                {
                    "saturation": 36
                },
                {
                    "color": "#333333"
                },
                {
                    "lightness": 40
                }
            ]
        },
        {
            "elementType": "labels.icon",
            "stylers": [
                {
                    "visibility": "off"
                }
            ]
        },
        {
            "featureType": "transit",
            "elementType": "geometry",
            "stylers": [
                {
                    "color": "#f2f2f2"
                },
                {
                    "lightness": 19
                }
            ]
        },
        {
            "featureType": "administrative",
            "elementType": "geometry.fill",
            "stylers": [
                {
                    "color": "#fefefe"
                },
                {
                    "lightness": 20
                }
            ]
        },
        {
            "featureType": "administrative",
            "elementType": "geometry.stroke",
            "stylers": [
                {
                    "color": "#fefefe"
                },
                {
                    "lightness": 17
                },
                {
                    "weight": 1.2
                }
            ]
        }
    ];
    }

    self.mapInit = function(mapData){
      if (!mapData || !Array.isArray(mapData) || mapData.length === 0) {
        console.log('No valid map data available');
        return;
      }

      var centerLocation = self.getCenterLocation(mapData);
      var zoom = 3.8;

      if($('input[name="wpdp_search_location_country"]').length){
        zoom = 5;
      }

      if(!self.main_map){
        
        self.main_map = new google.maps.Map(
          document.getElementById('wpdp_map'),
          {
              zoom: zoom, 
              center: centerLocation,
              mapTypeControl: false,
              mapId: 'WPDP_MAP' // Required for AdvancedMarkerElement
          }
        );
        
        // Apply custom styles using StyledMapType
        const styledMapType = new google.maps.StyledMapType(
          self.mapsStyles(),
          { name: 'Styled Map' }
        );
        self.main_map.mapTypes.set('styled_map', styledMapType);
        self.main_map.setMapTypeId('styled_map');
          
      }else{
        self.main_map.setCenter(centerLocation);
        self.main_map.setZoom(zoom);
      }

      var my_map = self.main_map;

      // Create pin element for AdvancedMarkerElement
      if(!self.pin_element){
        const pinBackground = new google.maps.marker.PinElement({
          background: '#FF0000',
          borderColor: '#CC0000',
          glyphColor: '#FFFFFF',
          scale: 1.0
        });
        self.pin_element = pinBackground;
      }
        
      var infoWindow = new google.maps.InfoWindow;
      var global_markers = []; // Ensure global_markers is defined
      var seenLocations = {};
    
      mapData.forEach(function(loc) {
        var originalLocation = loc.latitude + ',' + loc.longitude;
        var offset = 0.0009; // Small offset value
    
        if (seenLocations[originalLocation]) {
          // Apply a small random offset to avoid overlapping
          loc.latitude = parseFloat(loc.latitude) + (Math.random() - 0.5) * offset;
          loc.longitude = parseFloat(loc.longitude) + (Math.random() - 0.5) * offset;
        } else {
          seenLocations[originalLocation] = true;
        }
    
        var location = { lat: parseFloat(loc.latitude), lng: parseFloat(loc.longitude) };
    
        // Create custom content for the marker
        const pinElement = document.createElement('div');
        pinElement.style.width = '20px';
        pinElement.style.height = '20px';
        pinElement.style.borderRadius = '50%';
        pinElement.style.backgroundColor = '#FF0000';
        pinElement.style.opacity = '0.6';
        pinElement.style.border = 'none';

        // Use AdvancedMarkerElement instead of deprecated Marker
        var marker = new google.maps.marker.AdvancedMarkerElement({
          position: location,
          map: self.main_map,
          content: pinElement
        });
    
        global_markers.push(marker);
        let timestamp = new Date(loc.timestamp * 1000);
        if (loc.inter1) {
          loc.inter1 = interLabels[loc.inter1] ? interLabels[loc.inter1] + ' ( '+loc.actor1+' )' : loc.inter1;
        }

        if (loc.inter2) {
          loc.inter2 = interLabels[loc.inter2] ? interLabels[loc.inter2] + ' ( '+loc.actor2+' )' : loc.inter2;
        }

        marker.addListener('click', function() {
          infoWindow.close();
          let locationString = loc.region+', '+loc.country+', '+loc.admin1+', '+loc.admin2+', '+loc.admin3+', '+loc.location;
          locationString = locationString.replace(', ,', ',');
          infoWindow.setContent(`
            <div style="color: #333; font-size: 16px; padding: 10px; line-height: 1.6; border: 2px solid #333; border-radius: 10px; background: #fff;">
              <h2 style="margin: 0 0 10px; font-size: 20px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                ${loc.disorder_type}
              </h2>
              <p style="margin-bottom:0;"><strong>Fatalities:</strong> ${loc.fatalities}`+(loc.fatalities > 0 ? ' From '+loc.event_type+'' : '')+`</p>
             
              <p style="margin-bottom:0;"><strong>Date:</strong> ${loc.event_date}</p>
              <div class="map_more_details">
                <span style="cursor:pointer;color:#cd0202;font-size:25px;margin-top:3px;" class="dashicons dashicons-info"></span>
                <div class="det">
                  <ul class="wpdp_more_info_map">
                    <li><b>Event ID:</b> <span>${loc.event_id_cnty}</span></li>
                    <li><b>Event Type:</b> <span>${loc.event_type}</span></li>
                    <li><b>Actor 1:</b> <span>${loc.inter1}</span></li>
                    ${loc.inter2 ? `<li><b>Actor 2:</b> <span>${loc.inter2 == '0' ? 'N/A' : loc.inter2}</span></li>` : ''}
                    <li><b>Sub Event Type:</b> <span>${loc.sub_event_type}</span></li>
                    <li><b>Source:</b> <span>${loc.source}</span></li>
                    <li><b>Location:</b> <span>${locationString}</span></li>
                    <li class="notes"><b>Notes:</b> <span>${loc.notes}</span></li>
                    <li><b>Timestamp:</b> <span>${timestamp.toISOString()}</span></li>
                  </ul>
                </div>
              </div>
            </div>
          `);
          infoWindow.open(self.main_map, marker);
        });
    
        self.main_map.addListener('click', function() {
          infoWindow.close();
        });
      });
    
      // Clear existing clusterer if it exists
      if (typeof self.markerClustererInstance !== 'undefined' && self.markerClustererInstance) {
        if (self.markerClustererInstance.clearMarkers) {
          self.markerClustererInstance.clearMarkers();
        }
      }
      
      // Initialize MarkerClusterer - the new library supports AdvancedMarkerElement
      if (typeof markerClusterer !== 'undefined' && markerClusterer.MarkerClusterer) {
        // New @googlemaps/markerclusterer library
        self.markerClustererInstance = new markerClusterer.MarkerClusterer({ 
          map: my_map, 
          markers: global_markers 
        });
      } else if (typeof MarkerClusterer !== 'undefined') {
        // Fallback to legacy MarkerClusterer if available
        self.markerClustererInstance = new MarkerClusterer(my_map, global_markers, {
          imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'
        });
      }


      // Date info panel
      const datePanel = document.createElement('div');
      datePanel.id = 'map-date-panel';
      var formattedFromYear = moment(fromYear).format('Do MMM YYYY');
      var formattedToYear = moment(toYear).format('Do MMM YYYY');
      var date_text = 'Events in '+($('input[name="wpdp_search_location_country"]').length > 0 ? $('input[name="wpdp_search_location_country"]').val() : 'all ICGLR Member States')+' from ' + formattedFromYear + ' to ' + formattedToYear;
      datePanel.innerHTML = '<p class="default-text">' + date_text + '</p>';
      document.getElementById('wpdp_map').appendChild(datePanel);

    },

    self.dataTables = function(){
      if ($.fn.DataTable && $('#wpdp_datatable').length > 0) {
        $('#wpdp-loader').css('display','flex');
        self.table = $('#wpdp_datatable').DataTable({
            ajax: {
              "url": wpdp_obj.ajax_url,
              "type": "POST",
              "data": function ( d ) {
                d.action = 'wpdp_datatables_request';
                d.type_val = selectedIncidents;
                d.from_val = fromYear;
                d.actors_val = selectedActors;
                d.actor_names_val = selectedActorNames;
                d.fat_val = selectedFat;
                d.to_val = toYear;
                d.locations_val = selectedLocations;
                d.target_civ = targetCiv;
              },
              "dataSrc": function(json) {
                if (!json.data || json.data.length === 0) {
                  $('.filter_data .no_data').show();
                  $('#wpdp-loader').hide();
                  return [];
                }
                return json.data;
              }
            },
            drawCallback: function(settings, json) {
              if($('#wpdp_chart').length <= 0){
                $('#wpdp-loader').hide();
              }
              // $('.wpdp .con').css('left','-152%').removeClass('active');
              // $('.wpdp .filter span').attr('class','fas fa-sliders-h');
            },

            processing: true,
            serverSide: true,
            searching: true,
            language: {
              searchPlaceholder: "Search Event ID",
              search: "" 
            },
            deferRender: true,
            pagingType: "full_numbers",
            lengthChange: false,
            pageLength: 20,
            dom: 'Bfrtip',
            buttons: [
              { 
                extend: 'copyHtml5',
                exportOptions: {
                    columns: [0,1,2,3]
                },
                action: function (e, dt, button, config) {
                  var selfy = this;
                  self.exportAllData(selfy, dt, button, config);
                }
              },
              {
                extend: 'excelHtml5',
                exportOptions: {
                    columns: [0,1,2,3]
                },
                action: function (e, dt, button, config) {
                  var selfy = this;
                  self.exportAllData(selfy, dt, button, config);
                }
              },
              { 
                extend: 'csvHtml5',
                exportOptions: {
                    columns: [0,1,2,3]
                },
                action: function (e, dt, button, config) {
                  var selfy = this;
                  self.exportAllData(selfy, dt, button, config);
                }
              },
              {
                extend: 'pdfHtml5',
                exportOptions: {
                    columns: [0,1,2,3]
                },
                action: function (e, dt, button, config) {
                  var selfy = this;
                  self.exportAllData(selfy, dt, button, config);
                }
              },
              {
                extend: 'print',
                exportOptions: {
                    columns: [0,1,2,3]
                },
				
				
                customize: function (win) {
                  // Get the chart as a base64 image
                  var canvas = document.getElementById('wpdp_chart');
                  var chartImage = canvas.toDataURL('image/png');

                  // Create an image element for the chart
                  var img = $('<img>').attr('src', chartImage).css({
                    width: '500px', // Adjust the width as needed
                    marginTop: '10px' // Add some space before the chart image
                  });

                  // Append the chart image after the table
                  $(win.document.body).find('table').after(img);
                }
              },


              ],
            "columnDefs": [
              { "orderable": false, "targets": 4 },
              {
                "targets": 0,
                "render": function(data, type, row) {
                  if (type === 'display' || type === 'filter') {
                    var date = new Date(data);
                    let day = date.getDate().toString().padStart(2, '0');

                    return day + ' ' + date.toLocaleString('default', { month: 'short' }) + ' ' + date.getFullYear();
                  }
                  return data;
                }
              }
            ],
          
            "createdRow": function(row, data, dataIndex) {
              $('td:eq(4)', row).html('<span event_id="'+data[4]+'" style="cursor:pointer;color:#cd0202;font-size:26px;" class="more-info dashicons dashicons-info"></span>');
            },

        });

        $('#wpdp_datatable_filter label').css('position','relative').append(wpdp_obj.search_info_icon);


      $('#wpdp_datatable tbody').on('click', 'span.more-info', function() {
          var tr = $(this).closest('tr');
          var row = self.table.row(tr);
      
          if (row.child.isShown()) {
              tr.next('tr').find('td').wrapInner('<div style="display: block;"></div>');
              tr.next('tr').find('td > div').slideUp(function() {
                 row.child.hide();
                 tr.removeClass('shown');
              });
          } else {
            format(row.selector.rows[0],row.child,tr)
          }
      });
    
      function format ( row, child, tr ) {
        let event_id = $(row).find('span[event_id]').attr('event_id');
        if(event_id == ''){
          return;
        }

        $.ajax({
            url: wpdp_obj.ajax_url,
            data: {
              action:'wpdp_datatables_find_by_id',
              event_id:event_id
            },
            type: 'POST',
            success: function(response) {

              if (response.data[0].inter1) {
                response.data[0].inter1 = interLabels[response.data[0].inter1] ? interLabels[response.data[0].inter1] + ' ( '+response.data[0].actor1+' )' : response.data[0].inter1;
              }

              if (response.data[0].inter2) {
                response.data[0].inter2 = interLabels[response.data[0].inter2] ? interLabels[response.data[0].inter2] + ' ( '+response.data[0].actor2+' )' : response.data[0].inter2;
              }

              let locationString = response.data[0].region+', '+response.data[0].country+', '+response.data[0].admin1+', '+response.data[0].admin2+', '+response.data[0].admin3+', '+response.data[0].location;
              locationString = locationString.replace(', ,', ',');
    
              
              var htmlContent = `
                <ul class="wpdp_more_info">
                    <li>
                      <b>Event ID:</b>
                      <span>`+response.data[0].event_id_cnty+`</span>
                    </li>
                    <li>
                      <b>Event Type:</b>
                      <span>`+response.data[0].event_type+`</span>
                    </li>
                    <li>
                      <b>Sub Event Type:</b>
                      <span>`+response.data[0].sub_event_type+`</span>
                    </li>
                    <li>
                      <b>Source Type:</b>
                      <span>`+response.data[0].source+`</span>
                    </li>
                    <li>
                      <b>Actor 1:</b>
                      <span>`+response.data[0].inter1+`</span>
                    </li>
                    `+(response.data[0].inter2 ? '<li><b>Actor 2: </b><span>'+(response.data[0].inter2 == '0' ? 'N/A' : response.data[0].inter2)+'</span></li>' : '')+`
                    <li>
                      <b>Fatalities:</b>
                      <span>`+(response.data[0].fatalities > 0 ? response.data[0].fatalities + ' from ' + response.data[0].event_type : response.data[0].fatalities)+`</span>
                    </li>
                    <li>
                      <b>Location:</b>
                      <span>`+locationString+`</span>
                    </li>
                    <li class="notes">
                      <b>Notes:</b>
                      <span>`+response.data[0].notes+`</span>
                    </li>
                    <li>
                      <b>Timestamp:</b>
                      <span>`+response.data[0].timestamp+`</span>
                    </li>
        
                  </ul>
              `;
                child(htmlContent).show();
                tr.next('tr').find('td').wrapInner('<div style="display: none;"></div>');
                tr.next('tr').find('td > div').slideDown();
                tr.addClass('shown');
            },
            error: function(errorThrown){
                alert('No data found');
            }
        });
      }
          
      }
    },

    // Add this new method to your self object
    self.exportAllData = function(selfy, dt, button, config) {

      var oldStart = dt.settings()[0]._iDisplayStart;
      var oldOrder = dt.order();  // Store the current order

      
      dt.one('preXhr', function (e, s, data) {
          // Just this once, load all data from the server...
          data.start = 0;
          data.length = 2147483647;
          
          dt.one('preDraw', function (e, settings) {
              // Call the original action function
              if (button[0].className.indexOf('buttons-copy') >= 0) {
                  $.fn.dataTable.ext.buttons.copyHtml5.action.call(selfy, e, dt, button, config);
              } else if (button[0].className.indexOf('buttons-excel') >= 0) {
                  $.fn.dataTable.ext.buttons.excelHtml5.available(dt, config) ?
                      $.fn.dataTable.ext.buttons.excelHtml5.action.call(selfy, e, dt, button, config) :
                      $.fn.dataTable.ext.buttons.excelFlash.action.call(selfy, e, dt, button, config);
              } else if (button[0].className.indexOf('buttons-csv') >= 0) {
                  $.fn.dataTable.ext.buttons.csvHtml5.available(dt, config) ?
                      $.fn.dataTable.ext.buttons.csvHtml5.action.call(selfy, e, dt, button, config) :
                      $.fn.dataTable.ext.buttons.csvFlash.action.call(selfy, e, dt, button, config);
              } else if (button[0].className.indexOf('buttons-pdf') >= 0) {
                  $.fn.dataTable.ext.buttons.pdfHtml5.available(dt, config) ?
                      $.fn.dataTable.ext.buttons.pdfHtml5.action.call(selfy, e, dt, button, config) :
                      $.fn.dataTable.ext.buttons.pdfFlash.action.call(selfy, e, dt, button, config);
              } else if (button[0].className.indexOf('buttons-print') >= 0) {
                  $.fn.dataTable.ext.buttons.print.action(e, dt, button, config);
              }
              dt.one('preXhr', function (e, s, data) {
                  // DataTables thinks the first item displayed is index 0, but we're not drawing that.
                  // Set the property to what it was before exporting.
                  settings._iDisplayStart = oldStart;
                  data.start = oldStart;
              });
              // Reload the grid with the original page. Otherwise, API functions like table.cell(this) don't work properly.
              setTimeout(dt.ajax.reload, 0);
              // Prevent rendering of the full data to the DOM
              return false;
          });
      });
      // Requery the server with the new one-time export settings
      dt.ajax.reload();
    },


    self.menuFilters = function(){

      setTimeout(function() {
          $('.wpdp .filter_data').show();
      }, 500);

      if ($(window).width() <= 1200) {
        $('.wpdp .filter span').removeClass('fa-arrow-left').addClass('fa-sliders-h');
      }

      $('.filter_data .grp:not(.locations) li:not(:has(li))').find('.dashicons').remove();


      $(document).click(function(e) {
        if ($('#wpdp_from').datepicker('widget').is(':visible') ||
            $(e.target).closest('.select2-container').length) {
            // Don't do anything if datepicker or select2 dropdown is visible
            return;
        }
        // if (!$(e.target).closest('.wpdp .con').length && 
        //     !$(e.target).hasClass('hasDatepicker') && 
        //     !$(e.target).closest('.ui-datepicker').length && 
        //     !$(e.target).hasClass('select2-selection__choice__remove') &&
        //     !$(e.target).hasClass('ui-datepicker-trigger') &&
        //     !$(e.target).closest('.select2-selection').length) {
        //     $('.wpdp .con').css('left','-152%').removeClass('active');
        //     $('.wpdp .filter span').attr('class','fas fa-sliders-h');
        // }
      });

    
      $('.wpdp .filter').click(function(e){
        e.preventDefault();
        e.stopPropagation();

        if($(this).find('span').hasClass('fa-arrow-left')){
          // Closing filter menu
          $('.wpdp .con').animate({marginLeft:'-270px'},1).hide().removeClass('active');
          $('.wpdp .filter span').attr('class','fas fa-sliders-h');
          $('.wpdp_filter_content').animate({marginLeft:'0',width:'100%'},200, function() {
            // After animation, shift center point left to compensate for closed menu
            if (self.main_map) {
              var center = self.main_map.getCenter();
              if (center) {
                self.main_map.setCenter(new google.maps.LatLng(
                  center.lat(),
                  center.lng() - 12 // Adjust this value to shift more/less left
                ));
              }
            }


            if (self.poly_map) {
              var center = self.poly_map.getCenter();
              if (center) {
                self.poly_map.setCenter(new google.maps.LatLng(
                  center.lat(),
                  center.lng() - 28 // Adjust this value to shift more/less left
                ));
              }
            }

          });
          $('#map-info-panel,#map-legend-panel,#map-date-panel').css('right','60px');
          $
        } else {
          // Opening filter menu
          $('.wpdp .con').animate({marginLeft:'0'},200).show().addClass('active');
          $(this).find('span').attr('class','fas fa-arrow-left');
          $('#map-info-panel,#map-legend-panel,#map-date-panel').css('right','290px');
          if ($('.wpdp_filter_content').hasClass('maps')) {
              $('.wpdp_filter_content').animate({marginLeft:'270px',width:'100%'},200, function() {
                // After animation, shift center point right to compensate for opened menu
                if (self.main_map) {
                  var center = self.main_map.getCenter();
                  if (center) {
                    self.main_map.setCenter(new google.maps.LatLng(
                      center.lat(),
                      center.lng() + 12 // Adjust this value to shift more/less right
                    ));
                  }
                }

                if (self.poly_map) {
                  var center = self.poly_map.getCenter();
                  if (center) {
                    self.poly_map.setCenter(new google.maps.LatLng(
                      center.lat(),
                      center.lng() + 28 // Adjust this value to shift more/less right
                    ));
                  }
                }

              });
          } else {
              $('.wpdp_filter_content').animate({marginLeft:'270px',width:'80%'},200);
          }
        }
      });
    

    },

    self.filtersChange = function() {
      $('.wpdp #filter_form').on('submit',function(e){
        e.preventDefault();
        $('#wpdp-loader').css('display','flex');
        self.filterAction();

        // Session save.
        var filterData = {};
        $(this).find('input, select').each(function() {
            var $this = $(this);
            if ($this.is(':checkbox') || $this.is(':radio')) {
                if ($this.is(':checked') && !filterData[$this.attr('name')]) {
                    filterData[$this.attr('name')] = $this.val();
                }
            } else {
                if ($this.val() !== '') {
                  if($this.attr('name') == 'wpdp_from' && !$('#wpdp_from').hasClass('changed')){
                    return true;
                  }
                  if($this.attr('name') == 'wpdp_to' && !$('#wpdp_to').hasClass('changed')){
                    return true;
                  }

                  if(!filterData[$this.attr('name')]){
                    filterData[$this.attr('name')] = $this.val();
                  }
                }
            }
        });

        $.ajax({
            url: wpdp_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'save_filter_choices',
                filter_data: filterData
            },
            success: function(response) {
                if (response.success) {

                } else {
                    console.error('Failed to save filter choices');
                }
            }
        });
      });
    },

    self.filterAction = function(){

      self.setDefaultFilters();

      if ($.fn.DataTable && $('#wpdp_datatable').length > 0) {
        self.table.draw(false);
      }

      self.graphChange();

      if (typeof google === 'object' && typeof google.maps === 'object') {
        self.mapChange();
      }


    },

    self.mapChange = function(){
      let newMapType = $('input[name="wpdp_search_location_country"]').length > 0 ? 
      ($('input[name="wpdp_search_location_country"]').val() == '' ? 'polygons' : 'points') : 'points';
  

      // Only run AJAX if map type has changed
      if (newMapType !== self.currentMapType) {
        $('.checkboxes_locations').html('Loading Locations...');
        $.ajax({
          url: wpdp_obj.ajax_url,
          type: 'POST',
          data: {
            action: 'get_locations_html',
            'search_location_country': newMapType === 'polygons' ? '' : $('input[name="wpdp_search_location_country"]').val(),
            'atts': wpdp_shortcode_atts
          },
          success: function(response){
            $('.checkboxes_locations').html(response);
          }
        });
        self.currentMapType = newMapType;
      }

      if(newMapType === 'polygons') {
        $('#wpdp_map').hide();
        $('#polygons_map').show();
        $('.wpdp_maps_only').hide();
        self.maps_polygons();
      } else {
        $('.wpdp_maps_only').show().css('display','inline-block');
        $('#polygons_map').hide();
        $('#wpdp_map').show();

        for(let i=0; i<global_markers.length; i++){
          global_markers[i].map = null;
        }
        if(self.markerClustererInstance){
          if (self.markerClustererInstance.clearMarkers) {
            self.markerClustererInstance.clearMarkers();
          }
          self.markerClustererInstance = null;
        }
        global_markers = [];
        self.maps();
      }
    },
      
    self.graphChange = function(){
      
      if (typeof Chart === 'undefined') {
        return;
      }

      $('#wpdp-loader').css('display','flex');


      $.ajax({
        url: wpdp_obj.ajax_url,
        data: {
          action:'wpdp_graph_request',
          type_val: selectedIncidentsGraphs,
          locations_val: selectedLocations,
          actors_val: selectedActors,
          actor_names_val: selectedActorNames,
          fat_val: selectedFatGraphs,
          from_val: fromYear,
          to_val: toYear,
          timeframe: timeframe,
          target_civ: targetCiv
        },
        type: 'POST',
        success: function(response) {

          // Check if all datasets are empty
          const hasIncidentData = Object.keys(response.data.data).some(key => 
            Object.keys(response.data.data[key]).some(date => response.data.data[key][date].events_count > 0)
          );
          
          const hasFatalityData = Object.keys(response.data.data).some(key =>
            Object.keys(response.data.data[key]).some(date => response.data.data[key][date].fatalities_count > 0)
          );
          
          const hasActorData = Object.keys(response.data.data_actors).some(key =>
            Object.keys(response.data.data_actors[key]).some(date => response.data.data_actors[key][date].events_count > 0)
          );

          
          
          // Check if toYear falls in the last month
          const toYearDate = new Date(toYear);
          const currentDate = new Date();
          const oneMonthAgo = new Date();
          oneMonthAgo.setMonth(currentDate.getMonth() - 1);
          const isInLastMonth = toYearDate >= oneMonthAgo && toYearDate <= currentDate;

          // Hide loader
          $('#wpdp-loader').hide();

          // Handle incidents graph
          if (!hasIncidentData) {
            $('#wpdp_chart,.last_updated_chart.chart').addClass('wpdp_force_hide').removeClass('wpdp_force_show');
            $('.no-chart-data-message').remove();
            $('#wpdp_chart').after('<div class="no-chart-data-message" style="text-align: center; padding: 20px;">No incidents found. Please adjust the filter options to see more data.</div>');
            $('.chart-controls.chart').addClass('wpdp_force_hide').removeClass('wpdp_force_show');
          } else {
            $('#wpdp_chart').removeClass('wpdp_force_hide').addClass('wpdp_force_show');
            $('.chart-controls.chart').removeClass('wpdp_force_hide').addClass('wpdp_force_show');
            
            if(isInLastMonth){
              setTimeout(() => {
                $('.last_updated_chart.chart').removeClass('wpdp_force_hide').addClass('wpdp_force_show');
              }, 500);
            }else{
              $('.last_updated_chart.chart').removeClass('wpdp_force_show').addClass('wpdp_force_hide');
            }

            $('.no-chart-data-message').remove();
          }

          // Handle fatalities graph
          if (!hasFatalityData) {
            $('#wpdp_chart_fat,.last_updated_chart.chart_fat').removeClass('wpdp_force_show').addClass('wpdp_force_hide');
            $('.no-fat-data-message').remove();
            $('#wpdp_chart_fat').after('<div class="no-fat-data-message" style="text-align: center; padding: 20px;">No fatalities found. Please adjust the filter options to see more data.</div>');
            $('.chart-controls.fat_chart').addClass('wpdp_force_hide').removeClass('wpdp_force_show');
          } else {
            $('#wpdp_chart_fat').removeClass('wpdp_force_hide').addClass('wpdp_force_show');
            $('.chart-controls.fat_chart').removeClass('wpdp_force_hide').addClass('wpdp_force_show');

            if(isInLastMonth){
              setTimeout(() => {
                $('.last_updated_chart.chart_fat').removeClass('wpdp_force_hide').addClass('wpdp_force_show');
              }, 500);
            }else{
              $('.last_updated_chart.chart_fat').removeClass('wpdp_force_show').addClass('wpdp_force_hide');
            }


            $('.no-fat-data-message').remove();
          }

          // Handle actors graph
          if (!hasActorData) {
            $('#wpdp_chart_bar_chart,.last_updated_chart.chart_bar').removeClass('wpdp_force_show').addClass('wpdp_force_hide');
            $('.no-actors-data-message').remove();
            $('#wpdp_chart_bar_chart').after('<div class="no-actors-data-message" style="text-align: center; padding: 20px;">No actors found. Please adjust the filter options to see more data.</div>');
          } else {
            $('#wpdp_chart_bar_chart').removeClass('wpdp_force_hide').addClass('wpdp_force_show');

            if(isInLastMonth){
              setTimeout(() => {
                $('.last_updated_chart.chart_bar').removeClass('wpdp_force_hide').addClass('wpdp_force_show');
              }, 500);
            }else{
              $('.last_updated_chart.chart_bar').removeClass('wpdp_force_show').addClass('wpdp_force_hide');
            }

            $('.no-actors-data-message').remove();
          }

          var latest_date = moment(wpdp_filter_dates[wpdp_filter_dates.length - 1]).format('Do MMM YYYY');

          if(!isInLastMonth){
            latest_date = 0;
          }

          self.chartInit(response.data.data,response.data.data_actors,response.data.chart_sql,response.data.intervals,latest_date,response.data.most_recent_date,response.data.most_recent_fatal_date);
          $('#wpdp-loader').hide();

          if(response.data.count == 0){
            $('.filter_data .no_data').show();
          }
        },
        error: function(errorThrown){
            alert('No data found');
        }
      });
    }

    self.chartInit = function(data, data_actors, chart_sql, intervals, latest_date, most_recent_date, most_recent_fatal_date) {
      var datasets = [];
      var datasets_fat = [];
      var datasets_actors = [];
      const colors = [
        // Existing colors
        '#4dc9f6',
        '#f67019', 
        '#f53794',
        '#537bc4',
        '#acc236',
        '#166a8f',
        '#00a950',
        '#58595b',
        '#8549ba',
        '#ff9f40',
        '#ffcd56',
        '#36a2eb',
        '#9966ff',
        
        // New colors
        '#FF6B6B', // Coral red
        '#4ECDC4', // Turquoise mint
        '#45B7D1', // Ocean blue
        '#96CEB4', // Sage green
        '#D4A5A5', // Dusty rose
        '#9B4DCA', // Royal purple
        '#FFB347', // Pastel orange
        '#A5668B', // Plum
        '#69D2E7', // Sky blue
        '#F7D794', // Sand yellow
        '#E056FD', // Bright purple
        '#B8E994', // Light lime
        '#FF8C94', // Salmon pink
        '#88D8B0', // Mint green
        '#FFAAA5'  // Peach
    ];

      // Sort data keys based on checkbox levels
      const getLevelFromCheckbox = (label) => {
        label = label.toLowerCase();
        const checkbox = $(`input[type="checkbox"][label_value="${label}"]`).first();
        if (checkbox.hasClass('level_1')) return 1;
        if (checkbox.hasClass('level_2')) return 2;
        if (checkbox.hasClass('level_3')) return 3;
        return 4; // Default level for unknown items
      };



      // Helper function to check if any items exist at a specific level
      const hasItemsAtLevel = (data, level) => {
        return Object.keys(data).some(key => {
          key = key.toLowerCase();
          const checkbox = $(`input[type="checkbox"][label_value="${key}"]`).first();
          return checkbox.hasClass(`level_${level}`);
        });
      };

      const orderedData = {};
      Object.keys(data)
      .sort((a, b) => {
        const levelA = getLevelFromCheckbox(a);
        const levelB = getLevelFromCheckbox(b);
        return levelA - levelB;
      })
      .forEach(key => {
        orderedData[key] = data[key];
      });


      data = orderedData;

      let i =0;
      for(let label in data){ i++;
        let dataset = {
          label: label,
          borderColor: colors[i],
          fill: false,
          data: [],
          count: [],
          fat: [],
        };

        for(let val in data[label]){
          dataset.data.push({
            x: val, 
            y: data[label][val].events_count
          });
        }
        datasets.push(dataset);
      }


      i =0;
      for(let label in data_actors){i++;
        let dataset_actors = {
          label:label,
          borderColor: colors[i],
          fill: false,
          data: [],
          count:[],
          fat:[],
        };

        for(let val in data_actors[label]){
          dataset_actors.data.push({
            x: val, 
            y: data_actors[label][val].events_count
          });
        }

        datasets_actors.push(dataset_actors);
      }

      i =0;
      for(let label in data){ i++;
        let dataset_fat = {
          label:label,
          borderColor: colors[i],
          fill: false,
          data: [],
          count:[],
          fat:[],
        };

        for(let val in data[label]){
          dataset_fat.data.push({
            x: val, 
            y: data[label][val].fatalities_count
          });
        }

        datasets_fat.push(dataset_fat);
      }

      // Add this function to sort datasets by date
      const sortDatasetsByDate = (datasets) => {
        datasets.forEach(dataset => {
          dataset.data.sort((a, b) => new Date(a.x) - new Date(b.x));
        });
        return datasets;
      };

      // Sort all datasets before creating charts
      datasets = sortDatasetsByDate(datasets);
      datasets_fat = sortDatasetsByDate(datasets_fat);
      datasets_actors = sortDatasetsByDate(datasets_actors);

      // Info icon
      if (datasets && datasets[0] && datasets[0].data.length > 0 && latest_date != 0) {
        tippy('.last_updated_chart.chart .tippy-icon', {
          trigger: 'click',
          hideOnClick: true,
          touch: true,
          content: 'This date is the last data entry for this chart, according to the filters set. The last data entry from all available data in the database is: ' + latest_date
        });

        $('.chart .last_updated_chart_date').text(most_recent_date);


      }

      if (datasets_fat && datasets_fat[0] && datasets_fat[0].data.length > 0 && latest_date != 0) {
        tippy('.last_updated_chart.chart_fat .tippy-icon', {
          trigger: 'click',
          hideOnClick: true,
          touch: true,
          content: 'This date is the last data entry for this chart, according to the filters set. The last data entry from all available data in the database is: ' + latest_date
        });
        $('.chart_fat .last_updated_chart_date').text(most_recent_fatal_date);
      }

      if (datasets_actors && datasets_actors[0] && datasets_actors[0].data.length > 0 && latest_date != 0) {
        tippy('.last_updated_chart.chart_bar .tippy-icon', {
          trigger: 'click',
          hideOnClick: true,
          touch: true,
          content: 'This date is the last data entry for this chart, according to the filters set. The last data entry from all available data in the database is: ' + latest_date
        });

        $('.chart_bar .last_updated_chart_date').text(most_recent_date);

      }



      if (window.myChart) {
        window.myChart.destroy();
      }

      if (window.myChartBarFat) {
        window.myChartBarFat.destroy();
      }

      if (window.myChartBar) {
        window.myChartBar.destroy();
      }
      
      if(!document.getElementById('wpdp_chart')){
        return;  
      }

      let title_text = 'Events in all ICGLR Member States';
      if(selectedLocations.length > 0){
        title_text = 'Events in ';
        let countries = new Set();
        $.each(selectedLocations, function(index, value) {
          value = value.split('+');
          $.each(value, function(index, value) {
            if(value.indexOf('country') > 0){
              value = value.split('__');
              countries.add(value[0]);
            }
          });
        });
        let countriesArray = Array.from(countries);
        if (countriesArray.length > 1 && countriesArray.length < 12) {
          title_text += countriesArray.slice(0, -1).join(', ') + ' and ' + countriesArray.slice(-1);
        }else if(countriesArray.length >= 12){
          title_text = 'Events in all ICGLR Member States';
        } else {
          title_text += countriesArray[0];
        }
      }
        
      let ctx = document.getElementById('wpdp_chart').getContext('2d');
      let ctx_fat = document.getElementById('wpdp_chart_fat').getContext('2d');
      let ctx_bar = document.getElementById('wpdp_chart_bar_chart').getContext('2d');
      let title_text_fat = title_text.replace('Events', 'Fatalities by Events');
      let title_text_actors = title_text.replace('Events', 'Type of Actors by Events');

      // Create title configuration object with source text
      const titleConfig = {
        display: true,
        padding: {
          top: 10,
          bottom: 10
        },
        labels: {
          title: {
            text: title_text,
            align: 'start',
            font: {
              weight: 'bold',
              size: 14
            },
            color: '#000000'
          },
          source: {
            text: 'Source: ACLED',
            align: 'end',
            font: {
              size: 11
            },
            color: '#666666'
          }
        }
      };

      const titleConfigFat = {
        ...titleConfig,
        labels: {
          ...titleConfig.labels,
          title: {
            ...titleConfig.labels.title,
            text: title_text_fat
          }
        }
      };

      const titleConfigActors = {
        ...titleConfig,
        labels: {
          ...titleConfig.labels,
          title: {
            ...titleConfig.labels.title,
            text: title_text_actors
          }
        }
      };

      // Register custom title plugin
      const customTitlePlugin = {
        id: 'customTitle',
        beforeDraw: (chart) => {
          const { ctx, chartArea, options, width } = chart;
          if (!chartArea) return;
      
          const titleConfig = options.plugins.title;
          if (!titleConfig || !titleConfig.display) return;
      
          const { left, right } = chartArea;
          const { title } = titleConfig.labels;
          const isMobile = width <= 768;
          const maxWidth = Math.max(1, right - left - 20);

          ctx.save();
          ctx.textBaseline = 'top';

          ctx.font = `${title.font.weight} ${title.font.size}px sans-serif`;
          ctx.fillStyle = title.color;
          const words = title.text.split(/\s+/);
          const lines = [];
          let line = '';
          words.forEach((word) => {
            const candidate = line ? `${line} ${word}` : word;
            if (line && ctx.measureText(candidate).width > maxWidth) {
              lines.push(line);
              line = word;
            } else {
              line = candidate;
            }
          });
          if (line) lines.push(line);

          const centerTitle = isMobile || lines.length > 1;
          ctx.textAlign = centerTitle ? 'center' : 'left';
          const titleX = centerTitle ? width / 2 : left;
          const lineHeight = title.font.size + 6;
          lines.forEach((titleLine, index) => {
            ctx.fillText(titleLine, titleX, titleConfig.padding.top + index * lineHeight);
          });

          ctx.restore();
        },
        beforeLayout: (chart) => {
          const titleConfig = chart.options.plugins.title;
          if (!titleConfig || !titleConfig.display) return;
          const { title } = titleConfig.labels;
          const width = chart.width;
          const maxWidth = Math.max(1, width - 40);
          const ctx = chart.ctx;
          ctx.save();
          ctx.font = `${title.font.weight} ${title.font.size}px sans-serif`;
          let line = '';
          let lineCount = 0;
          title.text.split(/\s+/).forEach((word) => {
            const candidate = line ? `${line} ${word}` : word;
            if (line && ctx.measureText(candidate).width > maxWidth) {
              lineCount += 1;
              line = word;
            } else {
              line = candidate;
            }
          });
          if (line) lineCount += 1;
          ctx.restore();

          if (typeof chart.options.layout.padding === 'number') {
            chart.options.layout.padding = { top: chart.options.layout.padding };
          }
          if (!chart.options.layout.padding) chart.options.layout.padding = {};
          const requiredTopPadding = titleConfig.padding.top + lineCount * (title.font.size + 6) + 2;
          chart.options.layout.padding.top = Math.max(chart.options.layout.padding.top || 0, requiredTopPadding);
        },
      };

      // Register the plugin
      Chart.register(customTitlePlugin);

      self.graphFun(ctx, datasets, titleConfig, chart_sql, intervals, false);
      self.graphFunBar(ctx_fat, datasets_fat, titleConfigFat, intervals, chart_sql, true);
      self.graphFunBar(ctx_bar, datasets_actors, titleConfigActors, intervals, chart_sql, false);
    }

    self.graphFunBar = function(ctx,datasets,titleConfig,intervals,chart_sql,is_fat){
      var chartVar = 'myChartBar';
      if(is_fat){
        chartVar = 'myChartBarFat';
      }

      // Add check for mobile screen width
      const isMobile = window.innerWidth <= 768;
      const maxTicksLimit = isMobile ? 15 : 30;

      // Modify datasets to set "No recorded actors" to light grey
      datasets.forEach(function(dataset) {
        if (dataset.label === "No recorded actors") {
          dataset.backgroundColor = '#D3D3D3'; // Light grey color
        } else {
          dataset.backgroundColor = dataset.borderColor;
        }
        delete dataset.borderColor;
      });

      window[chartVar] = new Chart(ctx, {
        type: 'bar',
        data: {datasets:datasets},
        options: {
          responsive: true,
          maintainAspectRatio: false, // Changed to false
          height: 400, // Explicit height
          layout: {
            padding: {
              // Add extra padding at the top on mobile
              top: 20
            }
          },
          plugins: {
              title: titleConfig,
              tooltip: {
                callbacks: {
                  title: function(context) {
                    const timeUnit = chart_sql;
      
                    // Format date based on time unit
                    if (timeUnit === 'day' || timeUnit === 'week') {
                      return moment(context[0].parsed.x).format('MMM D YYYY');
                    } else {
                      return moment(context[0].parsed.x).format('MMMM YYYY');
                    }
                  },
                  label: function(context) {
                    let label = context.dataset.label || '';
                    if (label) {
                      label += ': '+ context.parsed.y;
                    }
                    if (context.parsed.y !== null && is_fat) {
                      label += ' Fatalities';
                    }
                    return label;
                  }
                }
              }
          },
          scales: {
              x: {
                type: 'time',
                time: {
                  unit: chart_sql,
                },
                stacked: true,
                bounds: 'ticks',
                ticks:{
                  maxTicksLimit: maxTicksLimit, // Use dynamic value based on screen width
                  stepSize: intervals 
                }
              },
              y: {
                type: 'linear',
                display: true,
                beginAtZero: true,
                stacked: true,
              }
          },
        }
      });

      if(is_fat) {
        self.initChartFilters('chart_fat', chartVar, datasets, 'fatalities');
      }
    };


    self.graphFun = function(ctx, datasets, titleConfig, chart_sql, intervals, is_fat) {
      var chartVar = 'myChart';
      if(is_fat) {
        chartVar = 'myChartFat';
      }

      // Add check for mobile screen width
      const isMobile = window.innerWidth <= 768;
      const maxTicksLimit = isMobile ? 15 : 30;

      window[chartVar] = new Chart(ctx, {
        type: 'line',
        data: {datasets: datasets},
        options: {
          spanGaps: true,
          responsive: true,
          maintainAspectRatio: false,
          height: 400,
          layout: {
            padding: {
              // Add extra padding at the top on mobile
              top: 10
            }
          },
          plugins: {
            title: titleConfig,
            tooltip: {
              callbacks: {
                title: function(context) {
                  const timeUnit = chart_sql;
                  if (timeUnit === 'day' || timeUnit === 'week') {
                    return moment(context[0].parsed.x).format('MMM D YYYY');
                  } else {
                    return moment(context[0].parsed.x).format('MMMM YYYY');
                  }
                },
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  if (context.parsed.y !== null) {
                    label += context.parsed.y + ' Events';
                  }
                  return label;
                }
              }
            }
          },
          scales: {
            x: {
              type: 'time',
              time: {
                unit: chart_sql,
              },
              ticks: {
                maxTicksLimit: maxTicksLimit,
                stepSize: intervals
              }
            },
            y: {
              type: 'linear',
              display: true,
              beginAtZero: true
            }
          },
        }
      });

      self.initChartFilters('chart', chartVar, datasets, 'incidents');
    }

    // Add new helper function to set default active buttons
    self.setDefaultActiveButtons = function($buttons, existingLevels, datasets, type) {
      // Count datasets for each level
      const levelCounts = {
          level_1: 0,
          level_2: 0,
          level_3: 0
      };

      datasets.forEach(dataset => {
          const label = type === 'fatalities' 
              ? 'fatalities from ' + dataset.label.toLowerCase()
              : dataset.label.toLowerCase();
              
          const selector = type === 'fatalities' 
              ? '.fatalities input[type="checkbox"]'
              : '.incident_type input[type="checkbox"]';
              
          const checkbox = $(selector).filter(function() {
              return label.includes($(this).attr('label_value').toLowerCase());
          }).first();

          if (checkbox.hasClass('level_1')) levelCounts.level_1++;
          if (checkbox.hasClass('level_2')) levelCounts.level_2++;
          if (checkbox.hasClass('level_3')) levelCounts.level_3++;
      });

      const topTwoLevelsCount = levelCounts.level_1 + levelCounts.level_2;

      if (topTwoLevelsCount < 5) {
          // If less than 5 datasets in levels 1 and 2 combined, activate all levels
          $buttons.filter('.top-level, .second-level, .third-level').trigger('click').addClass('active');
      } else if (existingLevels.size === 1 && existingLevels.has('level_3')) {
          // If only level 3 exists, activate third-level button
          $buttons.filter('.third-level').trigger('click').addClass('active');
      } else {
          // Default behavior: activate top and second level
          $buttons.filter('.top-level, .second-level').trigger('click').addClass('active');
          $buttons.filter('.third-level.active').trigger('click').removeClass('active');
      }
    };

    self.initChartFilters = function(chartId, chartVar, datasets, type) {
      const $container = $(`#wpdp_${chartId}`).next();
      const $buttons = $container.find('.chart-filter-btn:not(.all-categories)');
      const $allButton = $container.find('.chart-filter-btn.all-categories');
  
      // First, determine what levels exist in the datasets
      const existingLevels = new Set();
      datasets.forEach(dataset => {
          const label = type === 'fatalities' 
              ? 'fatalities from ' + dataset.label.toLowerCase()
              : dataset.label.toLowerCase();
              
          const selector = type === 'fatalities' 
              ? '.fatalities input[type="checkbox"]'
              : '.incident_type input[type="checkbox"]';
              
          const checkbox = $(selector).filter(function() {
              return label.includes($(this).attr('label_value').toLowerCase());
          }).first();
  
          if (checkbox.hasClass('level_1')) existingLevels.add('level_1');
          if (checkbox.hasClass('level_2')) existingLevels.add('level_2');
          if (checkbox.hasClass('level_3')) existingLevels.add('level_3');
      });

      // Get the dataset count
      const datasetCount = datasets.length;


      // First, remove all existing event handlers
      $buttons.off('click');
      $allButton.off('click');
  
      // Function to update chart based on active buttons
      const updateChart = () => {
          const activeTypes = [];
          $buttons.filter('.active').each(function() {
              if ($(this).hasClass('top-level')) activeTypes.push('top-level');
              if ($(this).hasClass('second-level')) activeTypes.push('second-level');
              if ($(this).hasClass('third-level')) activeTypes.push('third-level');
          });

          // Filter datasets based on all active types
          const filteredDatasets = datasets.filter(dataset => {
              const label = type === 'fatalities' 
                  ? 'fatalities from ' + dataset.label.toLowerCase()
                  : dataset.label.toLowerCase();
                  
              const selector = type === 'fatalities' 
                  ? '.fatalities input[type="checkbox"]'
                  : '.incident_type input[type="checkbox"]';
                  
              const checkbox = $(selector).filter(function() {
                  return label === $(this).attr('label_value').toLowerCase();
              }).first();
              
              const matchesActiveTypes = activeTypes.some(type => {
                  switch(type) {
                      case 'top-level': return checkbox.hasClass('level_1');
                      case 'second-level': return checkbox.hasClass('level_2');
                      case 'third-level': return checkbox.hasClass('level_3');
                      default: return false;
                  }
              });

              // Check if activeTypes contains both 'top-level' and 'second-level'
              const isTopAndSecondLevelActive = activeTypes.includes('top-level') && activeTypes.includes('second-level');
              // Exclude 'strategic development events' if both 'top-level' and 'second-level' are active
              if (isTopAndSecondLevelActive && label === 'Strategic Development Events') {
                  return false;
              }

              return matchesActiveTypes;
          });

          window[chartVar].data.datasets = filteredDatasets;
          window[chartVar].update();
  
          // Save state
          const state = {
              buttons: $buttons.map(function() {
                  return {
                      class: $(this).attr('class').split(' ')[1],
                      active: $(this).hasClass('active')
                  };
              }).get()
          };

          localStorage.setItem(`wpdp_chart_filter_${chartId}`, JSON.stringify(state));
      };
  
      // Individual level buttons click handler
      $buttons.on('click', function(event) {
          if(!event.originalEvent || !event.originalEvent.isTrusted){
            return;
          }
          const $clicked = $(this);
          // If this is the last active button, prevent deactivation
          if ($buttons.filter('.active').length === 1 && $clicked.hasClass('active')) {
            // Check if the event is triggered by a real user click
                const instance = tippy($clicked[0], {
                    content: "Sorry, at least one active category level must be selected to prevent an empty chart.",
                    placement: 'top',
                    trigger: 'manual',
                    hideOnClick: true,
                    touch: true,
                });
                instance.show();

                // Destroy the tippy instance when the user clicks on any other button
                $buttons.not($clicked).on('click', function() {
                    instance.destroy();
                });
            return;
          }
  
          $clicked.toggleClass('active');
          updateChart();
      });
  
      // Show All button click handler
      $allButton.on('click', function() {
          if ($buttons.filter('.active').length === $buttons.length) {
              // If all are active, reset to default state (top and second level)
              $buttons.removeClass('active');
              $buttons.filter('.top-level, .second-level').addClass('active');
          } else {
              // Activate all buttons
              $buttons.addClass('active');
          }
          updateChart();
      });
  
      // Initialize state
      const savedState = localStorage.getItem(`wpdp_chart_filter_${chartId}`);
      if (savedState) {
          try {
              const state = JSON.parse(savedState);
              state.buttons.forEach(button => {
                  $buttons.filter(`.${button.class}`)[button.active ? 'addClass' : 'removeClass']('active');
              });
              self.setDefaultActiveButtons($buttons, existingLevels, datasets, type);
          } catch (e) {
              // If there's an error parsing the saved state, set default based on existing levels
              self.setDefaultActiveButtons($buttons, existingLevels, datasets, type);
          }
      } else {
          // Default state based on existing levels
          self.setDefaultActiveButtons($buttons, existingLevels, datasets, type);
      }
      
      // Initial chart update
      updateChart();
  };


    self.maps_polygons = function() {

      $('#wpdp-loader').css('display','flex');
      var selectedCountries = $('input[name="wpdp_country"]:radio').map(function() {
        return this.value;
      }).get();

      $.ajax({
        url: wpdp_obj.ajax_url,
        data: {
          action: 'wpdp_get_country_polygons_data',
          type_val: selectedIncidents,
          locations_val: selectedCountries,
          actors_val: selectedActors,
          actors_names_val: selectedActorNames,
          fat_val: selectedFat,
          from_val: fromYear,
          to_val: toYear,
          target_civ: targetCiv
        },
        type: 'POST',
        success: function(response) {
          $('#wpdp-loader').hide();
          
          // Filter out countries with 0 events and sort by event count
          const sortedCountries = [...response.data.data]
            .filter(country => country.events_count > 0)
            .sort((a, b) => b.events_count - a.events_count);
          
          // Calculate thresholds based on the data distribution
          const maxEvents = sortedCountries[0] ? sortedCountries[0].events_count : 0;
          const thresholds = {
            LARGE: Math.round(maxEvents * 0.4), // Countries with more than 40% of max are "high"
            MEDIUM: Math.round(maxEvents * 0.1)  // Countries with more than 10% of max are "medium"
          };

          // Ensure minimum thresholds
          if (maxEvents > 100) {
            thresholds.MEDIUM = Math.max(thresholds.MEDIUM, 50);  // At least 50 events for medium
            thresholds.LARGE = Math.max(thresholds.LARGE, 150);   // At least 150 events for high
          }

          // Add fixed info panel to map
          const infoPanel = document.createElement('div');
          infoPanel.id = 'map-info-panel';
          infoPanel.innerHTML = '<p class="default-text">Hover over a country to see details</p>';
          
          // Date info panel
          const datePanel = document.createElement('div');
          datePanel.id = 'map-date-panel';
          var formattedFromYear = moment(fromYear).format('Do MMM YYYY');
          var formattedToYear = moment(toYear).format('Do MMM YYYY');
          var date_text = 'Events in all ICGLR Member States from ' + formattedFromYear + ' to ' + formattedToYear;
          datePanel.innerHTML = '<p class="default-text">' + date_text + '</p>';

          
          self.poly_map = new google.maps.Map(document.getElementById('polygons_map'), {
            zoom: 5.5,
            mapTypeControl: false,
            mapId: 'WPDP_POLYGONS_MAP' // Required for AdvancedMarkerElement
          });
          
          // Apply custom styles using StyledMapType
          const styledMapType = new google.maps.StyledMapType(
            self.mapsStyles(),
            { name: 'Styled Map' }
          );
          self.poly_map.mapTypes.set('styled_map', styledMapType);
          self.poly_map.setMapTypeId('styled_map');
          
          // Change how we add the info panel to the map
          document.getElementById('polygons_map').appendChild(infoPanel);
          document.getElementById('polygons_map').appendChild(datePanel);

          // Set default styling for all features
          self.poly_map.data.setStyle({
            fillColor: '#FFEB3B',
            fillOpacity: 0.35,
            strokeColor: '#000000',
            strokeWeight: 1,
            visible: true
          });

          $.getJSON(wpdp_obj.url+'/lib/filtered_countries2.geojson', function(geoJson) {

            self.poly_map.data.addGeoJson(geoJson);

            // After adding GeoJSON, apply specific styling
            self.poly_map.data.setStyle(function(feature) {
              const countryName = feature.getProperty('ADMIN');
              const countryData = response.data.data.find(c => c.country === countryName);
              
              if (!countryData) {
                console.log('No data for country:', countryName); // Debug log
                return {
                  fillColor: '#CCCCCC', // Gray color for no data
                  fillOpacity: 0.35,
                  strokeColor: '#000000',
                  strokeWeight: 1,
                  visible: true // Changed to always be visible
                };
              }

              const color = self.getColorForIntensity(countryData.events_count, thresholds);

              return {
                fillColor: color,
                fillOpacity: 0.35,
                strokeColor: '#000000',
                strokeWeight: 1,
                visible: true
              };
            });

            // Add bounds fitting to ensure polygons are visible
            var bounds = new google.maps.LatLngBounds();
            self.poly_map.data.forEach(function(feature) {
              self.processPoints(feature.getGeometry(), bounds.extend, bounds);
            });
            self.poly_map.fitBounds(bounds);

            // Create custom label for Republic of Congo using AdvancedMarkerElement
            const congoLabelContent = document.createElement('div');
            congoLabelContent.innerHTML = 'Republic of<br>the Congo';
            congoLabelContent.style.color = '#AEA88F';
            congoLabelContent.style.fontSize = '12px';
            congoLabelContent.style.fontWeight = 'bold';
            congoLabelContent.style.textAlign = 'center';
            congoLabelContent.style.backgroundColor = 'transparent';
            congoLabelContent.style.padding = '0';
            congoLabelContent.className = 'map-label';

            const congoLabel = new google.maps.marker.AdvancedMarkerElement({
              position: { lat: -0.228021, lng: 15.827659 }, // Approximate center of Republic of Congo
              map: self.poly_map,
              content: congoLabelContent
            });

            // Add zoom change listener to show/hide the label
            const ZOOM_THRESHOLD = 5; // Adjust this value based on when you want the label to hide
            google.maps.event.addListener(self.poly_map, 'zoom_changed', function() {
              const currentZoom = self.poly_map.getZoom();
              // AdvancedMarkerElement uses map property to show/hide
              congoLabel.map = (currentZoom < ZOOM_THRESHOLD) ? self.poly_map : null;
            });

            // Initial visibility check
            congoLabel.map = (self.poly_map.getZoom() < ZOOM_THRESHOLD) ? self.poly_map : null;

            // To shift the center after bounds are set, add this:
            google.maps.event.addListenerOnce(self.poly_map, 'bounds_changed', function() {
              var center = self.poly_map.getCenter();
              var lng = center.lng();
              if($('.wpdp .filter').find('span').hasClass('fa-arrow-left')){
                lng = lng + 25;
              }
              self.poly_map.setCenter(new google.maps.LatLng(
                center.lat(),
                lng
              ));

              // set zoom to 5.5
              self.poly_map.setZoom(4);
            });

          }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Failed to load GeoJSON:', textStatus, errorThrown);
          });

          // Replace infoWindow with info panel updates
          self.poly_map.data.addListener('mouseover', function(event) {
            self.poly_map.data.overrideStyle(event.feature, {
              fillOpacity: 0.7,
              strokeColor: "#FF0000",
              strokeWeight: 2
            });

            const countryName = event.feature.getProperty('ADMIN');
            const countryData = response.data.data.find(c => c.country === countryName);
            
            infoPanel.style.display = 'block';
            if (!countryData) {
              infoPanel.innerHTML = `
                <div class="country-info">
                  <h3 style="margin: 0 0 10px 0; font-size: 16px;">${countryName}</h3>
                  <p style="margin: 5px 0; color: #666;">
                    No events were found in this region,<br> please adjust the filter options to see more data.
                  </p>
                </div>
              `;
            } else {
              const severityLabel = self.getSeverityLabel(countryData.events_count, thresholds);
              const severityColor = self.getColorForIntensity(countryData.events_count, thresholds);
              
              infoPanel.innerHTML = `
                <div class="country-info">
                  <h3 style="margin: 0 0 10px 0; font-size: 16px;">${countryData.country}</h3>
                  <p style="margin: 5px 0;">
                    <span>Severity Level:</span>
                    <strong style="color: ${severityColor}">${severityLabel}</strong>
                  </p>
                  <p style="margin: 5px 0;">
                    <span>Total Incidents:</span>
                    <strong>${countryData.events_count.toLocaleString()}</strong>
                  </p>
                  <p style="margin: 5px 0;">
                    <span>Fatalities:</span>
                    <strong>${countryData.fatalities_count.toLocaleString()}</strong>
                  </p>
                </div>
              `;
            }
          });

          self.poly_map.data.addListener('mouseout', function(event) {
            self.poly_map.data.revertStyle();
            infoPanel.style.display = 'none';
          });

          self.poly_map.data.addListener('click', function(event) {
            const countryName = event.feature.getProperty('ADMIN');
            $('input[name="wpdp_country"][value="' + countryName + '"]:radio').prop('checked', true);
            $('input[name="wpdp_search_location_country"]').val(countryName).parents('form').submit();
          });

          // Add legend panel
          const legendPanel = document.createElement('div');
          legendPanel.id = 'map-legend-panel';
          
          // Update the legend content based on thresholds
          const updateLegend = function(maxEvents, thresholds) {
            return `
              <div class="legend-content">
                <h4 style="margin: 0 0 8px 0; font-size: 14px;">Risk Level</h4>
                <div class="legend-item">
                  <span class="color-box" style="background-color: #CD6155"></span>
                  <span class="legend-text">High: ${thresholds.LARGE.toLocaleString()}+ events</span>
                </div>
                <div class="legend-item">
                  <span class="color-box" style="background-color: #F0B27A"></span>
                  <span class="legend-text">Medium: ${thresholds.MEDIUM.toLocaleString()}-${(thresholds.LARGE-1).toLocaleString()} events</span>
                </div>
                <div class="legend-item">
                  <span class="color-box" style="background-color: #F9E79F"></span>
                  <span class="legend-text">Low: 1-${(thresholds.MEDIUM-1).toLocaleString()} events</span>
                </div>
                <div class="legend-item">
                  <span class="color-box" style="background-color: #CCCCCC"></span>
                  <span class="legend-text">No events found</span>
                </div>
              </div>
            `;
          };

          legendPanel.innerHTML = updateLegend(maxEvents, thresholds);
          document.getElementById('polygons_map').appendChild(legendPanel);

          // Update mouseover event to hide legend
          self.poly_map.data.addListener('mouseover', function(event) {
            // ... existing mouseover code ...
            legendPanel.style.display = 'none'; // Hide legend when showing country info
          });

          // Update mouseout event to show legend
          self.poly_map.data.addListener('mouseout', function(event) {
            self.poly_map.data.revertStyle();
            infoPanel.style.display = 'none';
            legendPanel.style.display = 'block'; // Show legend when not hovering over country
          });

        },
        error: function(errorThrown) {
          console.error('Failed to load country data:', errorThrown);
        }
      });
    };

    // Add this helper function to process points for bounds
    self.processPoints = function(geometry, callback, thisArg) {
      if (geometry instanceof google.maps.LatLng) {
        callback.call(thisArg, geometry);
      } else if (geometry instanceof google.maps.Data.Point) {
        callback.call(thisArg, geometry.get());
      } else {
        geometry.getArray().forEach(function(g) {
          self.processPoints(g, callback, thisArg);
        });
      }
    };

    self.getColorForIntensity = function(count, thresholds) {
      const COLORS = {
        LARGE: '#CD6155',    // Red for high count
        MEDIUM: '#F0B27A',   // Orange for medium count
        SMALL: '#F9E79F'     // Yellow for low count
      };

      // Add minimum threshold to prevent very low counts from being marked as high
      const MIN_THRESHOLD = 5; // Adjust this value based on your needs

      if (count < MIN_THRESHOLD) {
        return COLORS.SMALL;
      }

      if (count >= thresholds.LARGE) {
        return COLORS.LARGE;
      } else if (count >= thresholds.MEDIUM) {
        return COLORS.MEDIUM;
      } else {
        return COLORS.SMALL;
      }
    };

    self.getSeverityLabel = function(count, thresholds) {
      // Add minimum threshold to prevent very low counts from being marked as high
      const MIN_THRESHOLD = 5; // Adjust this value based on your needs

      if (count < MIN_THRESHOLD) {
        return 'Low';
      }


      if (count >= thresholds.LARGE) {
        return 'High';
      } else if (count >= thresholds.MEDIUM) {
        return 'Medium';
      } else {
        return 'Low';
      }
    };

    self.mapsChoice = function(){
      setTimeout(() => {
        if($('input[name="wpdp_country"]:radio').length > 0){
          self.maps_polygons();
        }else{
          self.maps();
        }
      }, 100);
    }

    // Add this new function to handle lazy loading of location levels
    self.loadLocationLevel = function(parentElement, parentKey) {
      $.ajax({
        url: wpdp_obj.ajax_url,
        type: 'POST',
        data: {
          action: 'get_location_level',
          parent_key: parentKey,
          country: $('input[name="wpdp_search_location_country"]').length ? $('input[name="wpdp_search_location_country"]').val() : ''
        },
        beforeSend: function() {
          $('.checkboxes_locations').css('opacity','0.5').css('pointer-events', 'none');
        },
        success: function(response) {
          if (response.success) {
            parentElement.find('> ul').remove();
            parentElement.append(response.data);
          } else {
            parentElement.find('> ul').html('<li>No data found</li>');
          }
          $('.checkboxes_locations').css('opacity','1').css('pointer-events', 'auto');
        },
        error: function() {
          parentElement.find('> ul').html('<li>Error loading data</li>');
        }
      });
    };

    // Modify the expandable click handler
    $(document).on('click', '.exp_click', function(e) {
      if ($(this).prev().is(':radio')) {
        return;
      }

      var $li = $(this).closest('li');
      var $arrow = $(this).find('.arrow');
      if($(this).parents('.grp').hasClass('locations')){
        if (!$li.hasClass('loaded')) {
          // Get the parent location key
          var parentKey = $li.find('> input').val();
          // Load child locations
          self.loadLocationLevel($li, parentKey);
          
          $li.addClass('loaded');
        }
      }
      
      $li.toggleClass('expanded');
      $arrow.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    $( self.init );
    window.wpdp_map = self.mapsChoice;

    
    self.restoreChartControlState = function(chartId) {
        const savedState = localStorage.getItem(`wpdp_chart_filter_${chartId}`);
        if (savedState) {
            const $buttons = $(`#wpdp_${chartId}`).next().find('.chart-filter-btn');
            $buttons.removeClass('active');
            $buttons.filter(`.${savedState}`).addClass('active');
            return savedState;
        }
        return 'top-level'; // default state
    };

    self.filterDatasets = function(datasets, filterType, type) {
        return datasets.filter(dataset => {
            const label = type === 'fatalities' 
                ? 'fatalities from ' + dataset.label.toLowerCase()
                : dataset.label.toLowerCase();
                
            const selector = type === 'fatalities' 
                ? '.fatalities input[type="checkbox"]'
                : '.incident_type input[type="checkbox"]';
                
            const checkbox = $(selector).filter(function() {
                return label.includes($(this).attr('label_value').toLowerCase());
            }).first();

            switch(filterType) {
                case 'all':
                    return true;
                case 'top-level':
                    return checkbox.hasClass('level_1');
                case 'second-level':
                    return checkbox.hasClass('level_2');
                case 'third-level':
                    return checkbox.hasClass('level_3');
                default:
                    return true;
            }
        });
    };

}( jQuery ) );
