
(function ($) {
  Drupal.behaviors.yourModule = {
    attach: function (context, settings) {

      // Initialize the Places Autocomplete input field.
      var input = document.getElementById('pac-input');
      const options = {
        fields: ["formatted_address", "name", "address_components"],
        componentRestrictions: {country: 'gb'},
        };
      var autocomplete = new google.maps.places.Autocomplete(input, options);

      // Add event listener for when a place is selected.
      autocomplete.addListener('place_changed', function () {
        var place = autocomplete.getPlace();
        // Do something with the selected place data.
        // Extract the Post town and Postal code from address components.
        var postTown = extractFromAddressComponents(place, 'postal_town');
        var postalCode = extractFromAddressComponents(place, 'postal_code');
        // Extract the street address from address components.
        var streetAddress = extractFromAddressComponents(place, 'route');
        var streetNumber = extractFromAddressComponents(place, 'street_number');
        var subLocality = extractFromAddressComponents(place, 'sublocality');
        var placeName = place.name

        // Combine street address and street number if both are available.
        var fullStreetAddress = (placeName + ' ' + streetNumber + ' ' + streetAddress + ' ' + subLocality).trim();

        var formatedAddres = place.formatted_address;
       
        // console.log("place name: " + place.name);

        // Iterate over each address component
        for (var i = 0; i < place.address_components.length; i++) {
          console.log('Address Component ' + (i + 1) + ':');

          // Print each key-value pair in the current address component
          for (var key in place.address_components[i]) {
            console.log('  ' + key + ':', place.address_components[i][key]);
          }
        }



      //   // Store the place data in Drupal form fields.
      //   document.getElementById('edit-payment-information-billing-information-address-0-address-address-line1').value = fullStreetAddress;
      //   document.getElementById('edit-payment-information-billing-information-address-0-address-locality').value = postTown;
      //   document.getElementById('edit-payment-information-billing-information-address-0-address-postal-code').value = postalCode;
      });
      // Helper function to extract data from address components.
      function extractFromAddressComponents(place, type) {
        for (var i = 0; i < place.address_components.length; i++) {
          for (var j = 0; j < place.address_components[i].types.length; j++) {
            // console.log(place.address_components[i].types[j]);
            if (place.address_components[i].types[j] === type) {
              return place.address_components[i].long_name;
            }
          }
        }
        return '';
      }
    }
  };
})(jQuery);
