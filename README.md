# Scandiweb_GdprScandiPWA

*Amasty GDPR integration for ScandiPWA*

## Features
- My Account page "Privacy Settings" tab gives the customer control over their privacy, just as the original Amasty extension
    - Download data
    - Anonymize data
    - Delete account
    - Manage consents
- Adds consent checkboxes to the registration component, as configured in the Amasty admin panel
- Replaces default checkout checkboxes with those configured in Amasty

## Usage
### Installation
- Install the Amasty GDPR extension. Tested with version 2.1.1
- Install the Scandiweb_GdprScandiPWA extension
- Add the GdprScandiPWA extension to your scandipwa.json configuration

### Configuration
This extension uses the Amasty configuration. Consult the Amasty Documentation for configuration.

### Extension
Since the default ScandiPWA theme does not implemente a "contact us" form nor a "subscribe to our newsletter" unlike Magento, this extension cannot automatically add consent checkboxes to these areas. If you have a custom implementation of these features, you can include checkboxes by following the steps described below.

#### Backend
Add a field to the GraphQl query of type `[consentUpdate]!`. Pass the value of this field to `\Scandiweb\GdprScandiPWA\Helper\ConsentUpdater::processConsents`, along with the GraphQl `$context` and `$area` (one of `["registration", "checkout", "contactus", "subscription"]`). 

#### Frontend

Use the `PrivacyConsentCheckboxes` component to wrap the submission button.
 
```jsx harmony
        <PrivacyConsentCheckboxes
            area={ /*<area>*/ }
            updateSelection={ /*<callback*/ }
        >
            { /*<submit button>*/ }
        </PrivacyConsentCheckboxes>;
```

Where:
- Area is one of the constants `AREA_REGISTRATION, AREA_CHECKOUT, AREA_CONTACT_US, AREA_SUBSCRIPTION` from src/scandipwa/app/util/Privacy.js, representing the area you want to use the checkboxes from
- The submit button (optional) is an element that should be hidden when the required checkboxes are not checked (will appear below the checkboxes)
- The callback (optional) is a function that will be called whenever the user updates the checkbox selection. It will be given two parameters:
    - An object where the keys are the codes of the checkboxes, and the boolean values are true iff the checkbox is checked
    - A boolean parameter that will be true iff all required checkboxes are selected
