# Strix Google Reviews Admin Panel

## Overview

This plugin provides a local admin interface for connecting Google Business Profiles to the main Strix Google Reviews plugin. Instead of using external services, users can connect their Google Business Profile directly through a local WordPress interface.

## Installation

1. Ensure the main Strix Google Reviews plugin is installed and activated
2. Install this plugin as a standard WordPress plugin
3. Activate the plugin

## Usage

### For Plugin Developer (Admin Settings)

The admin panel now includes comprehensive settings for developers:

1. **Navigate to Admin Menu** → **Strix Reviews Admin**
2. **Configure Settings**:
   - **Google Maps API Key**: Set your API key for Google Places and Maps integration
   - **Debug Mode**: Enable logging for troubleshooting
   - **Cache Settings**: Configure review data caching
   - **Review Limits**: Set maximum reviews to fetch
   - **Reply Settings**: Enable/disable business replies

3. **API Testing**: Test your Google Maps API key configuration
4. **Dashboard Monitoring**: View system status and connected profiles

### For End Users

1. Navigate to the Strix Google Reviews plugin settings in WordPress admin
2. Go to the "Free Widget Configurator" tab
3. Click the "Connect" button for Google Business Profile
4. A popup window will open with the local admin interface
5. Search for your business using the Google Places autocomplete
6. Select your business from the dropdown
7. Click "Connect" to link your profile
8. Reviews will be automatically retrieved and displayed

### Dashboard Features

Once connected, the admin panel provides a comprehensive dashboard with:

#### Business Profile Overview
- Business logo and basic information
- Current rating and total review count
- Address and contact details

#### Review Statistics
- Total number of reviews
- Average rating across all reviews
- Count of positive reviews (4+ stars)
- Recent reviews (last 7 days)

#### Review Management
- List of recent reviews with ratings
- Filter reviews by sentiment (All, Positive, Neutral, Negative)
- Review text and customer details
- Business replies (if any)
- Load more functionality for pagination

#### Visual Design
- Modern, responsive interface
- Gradient headers and card-based layout
- Interactive filtering and navigation
- Mobile-friendly design

### Features

- **Local Interface**: No external dependencies for the connection process
- **Google Places API**: Autocomplete search for business profiles
- **Review Retrieval**: Automatic fetching of reviews from connected profiles
- **Seamless Integration**: Works transparently with the main plugin

## Technical Details

### Files Structure

```
strix-google-reviews-admin/
├── strix-google-reviews-admin.php    # Main plugin file
├── index.php                         # Security file
├── readme.txt                        # Plugin documentation
├── USAGE.md                          # This file
└── assets/
    └── js/
        └── admin-custom.js           # Custom JavaScript
```

### Integration Points

The plugin integrates with the main Strix Google Reviews plugin through:

1. **JavaScript Hooks**: Modifies the connect button behavior
2. **AJAX Communication**: Handles data transfer between popup and main window
3. **WordPress Actions**: Uses standard WordPress plugin hooks

### API Endpoints

- `wp_ajax_strix_google_reviews_admin_connect`: Handles Google profile connection
- `wp_ajax_strix_google_reviews_admin_get_reviews`: Retrieves reviews (future use)

## Development

### Requirements

- WordPress 5.0+
- PHP 7.4+
- Main Strix Google Reviews plugin
- Google Places API key (configured in main plugin)

### Customization

The admin interface can be customized by modifying:

- `strix-google-reviews-admin.php`: Main functionality
- `assets/js/admin-custom.js`: JavaScript behavior
- CSS styling through WordPress enqueue system

## Troubleshooting

### Common Issues

1. **Plugin not activating**: Ensure main plugin is active first
2. **Popup not opening**: Check browser popup blockers
3. **Google API errors**: Verify API key is configured in main plugin

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support, please contact the Strix Media development team.