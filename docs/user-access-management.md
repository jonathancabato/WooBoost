# User Access Management Feature

## Overview

The WooBoost User Access Management feature provides role-based access control specifically for WooCommerce products. It restricts users with the `editor` role to only access WooCommerce product-related admin pages, preventing access to all other WordPress admin functionality.

## Features

### 1. Capability Management
- **Automatic Capability Assignment**: Automatically adds WooCommerce product capabilities to the `editor` role
- **Core Product Capabilities**:
  - `edit_products` - Edit products
  - `read_products` - Read products
  - `delete_products` - Delete products
  - `edit_others_products` - Edit others' products
  - `publish_products` - Publish products
  - `read_private_products` - Read private products
  - `delete_private_products` - Delete private products
  - `delete_published_products` - Delete published products
  - `delete_others_products` - Delete others' products
  - `edit_private_products` - Edit private products
  - `edit_published_products` - Edit published products

- **Excluded Capabilities** (to maintain strict access control):
  - `manage_product_terms` - Prevents access to categories/tags management
  - `edit_product_terms` - Prevents editing product terms
  - `delete_product_terms` - Prevents deleting product terms  
  - `assign_product_terms` - Prevents assigning product terms

- **Blocked Access**:
  - Product categories (`edit-tags.php?taxonomy=product_cat`)
  - Product tags (`edit-tags.php?taxonomy=product_tag`)
  - Product brands (`edit-tags.php?taxonomy=product_brand`)
  - Product attributes (`edit.php?post_type=product&page=product_attributes`)
  - Product reviews (`edit.php?post_type=product&page=product-reviews`)

### 2. Strict Access Control
- **Target Role**: `editor`
- **Allowed Pages**:
  - `wp-admin/edit.php?post_type=product` (Product list)
  - `wp-admin/post-new.php?post_type=product` (Add new product)
  - `wp-admin/post.php` (Edit existing products only)

### 3. Security Measures
- Automatic redirection for unauthorized access attempts
- Prevention of direct URL access to restricted pages
- AJAX request handling for seamless user experience
- No impact on other user roles (administrators, subscribers, etc.)

### 4. Custom Admin Interface
- **Custom Dashboard**: Product-focused dashboard for editors
- **Simplified Menu**: Only product-related menu items visible
- **Admin Bar Customization**: Removes unnecessary items, adds quick product creation
- **Product Statistics**: Overview of published and draft products

## Implementation Details

### Core Class: `WooBoost_User_Access`

#### Key Methods:

1. **`restrict_editor_access()`**
   - Main access control logic
   - Validates current page against allowed pages
   - Redirects unauthorized access to product list

2. **`remove_admin_menu_items()`**
   - Removes all default WordPress menu items
   - Removes WooCommerce menus except products

3. **`customize_editor_menu()`**
   - Adds custom product dashboard
   - Creates simplified product menu structure

4. **`redirect_unauthorized_access()`**
   - Secondary protection layer
   - Screen-based access validation

5. **`render_product_dashboard()`**
   - Custom dashboard interface
   - Product statistics and quick actions

### Security Features

#### Input Validation
- All GET/POST parameters are sanitized using `sanitize_text_field()`
- Post IDs are validated using `intval()`

#### Access Validation
- Multiple layers of role checking
- Protection against capability escalation
- AJAX request handling

#### Redirection Security
- Uses `wp_safe_redirect()` for secure redirections
- Prevents open redirect vulnerabilities

## Usage

### For Theme Integration
```php
// Add to functions.php
if (class_exists('WooCommerce')) {
    require_once 'path/to/class-wooboost-user-access.php';
}
```

### For Plugin Integration
```php
// Include in plugin initialization
if (is_admin() && class_exists('WooCommerce')) {
    new WooBoost_User_Access();
}
```

## User Experience

### Editor Role Experience
1. **Login**: Editors are redirected to the custom product dashboard
2. **Navigation**: Only product-related menu items are visible
3. **Access Attempts**: Unauthorized pages redirect to product list
4. **Dashboard**: Custom interface showing product statistics and quick actions

### Other Roles
- **Administrators**: Full access maintained (no restrictions)
- **Other Roles**: No impact on existing functionality

## Technical Requirements

### Dependencies
- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+

### WordPress Hooks Used
- `admin_init`: Primary access restriction
- `admin_menu`: Menu customization
- `wp_before_admin_bar_render`: Admin bar customization
- `current_screen`: Screen-based redirection

## Security Considerations

### Bypass Prevention
- Multiple validation layers prevent bypassing restrictions
- Screen-based validation as secondary protection
- AJAX request handling maintains security during dynamic operations

### Role Isolation
- Only affects `editor` role
- No impact on `administrator` or other roles
- Capability-based checking prevents privilege escalation

### Data Protection
- No sensitive data exposed in client-side code
- Secure parameter handling throughout

## Testing

### Test Cases
1. **Editor Access**: Verify editors can only access allowed pages
2. **Redirection**: Test unauthorized access attempts redirect properly
3. **Admin Functionality**: Ensure administrators retain full access
4. **AJAX Operations**: Verify dynamic operations work correctly
5. **Menu Display**: Confirm custom menu appears correctly

### Manual Testing
1. Create a user with `editor` role
2. Login and verify dashboard displays
3. Attempt to access restricted URLs directly
4. Test product creation and editing functionality

## Maintenance

### Regular Checks
- Monitor for new WooCommerce admin pages
- Update allowed page list as needed
- Test compatibility with WooCommerce updates

### Customization Points
- Modify `$allowed_pages` array to add/remove allowed pages
- Customize dashboard content in `render_product_dashboard()`
- Adjust menu items in `customize_editor_menu()`

## Troubleshooting

### Common Issues

#### "You need a higher level of permission" Error
**Problem**: Editors see "Sorry, you are not allowed to edit posts in this post type" when accessing product pages.

**Solution**: 
1. The editor role needs WooCommerce product capabilities
2. The plugin automatically adds these capabilities on activation
3. If the error persists:
   - Deactivate and reactivate the WooBoost plugin
   - Or manually trigger capability assignment by visiting any admin page after plugin activation

**Debug Test**: Add `?wooboost_test_caps=1` to any admin URL to test if capabilities are properly assigned.

#### Editors can't access products
**Cause**: WooCommerce capabilities not assigned to editor role
**Fix**: Plugin deactivation/reactivation or manual capability assignment

#### Redirects not working  
**Cause**: User role assignment issues
**Fix**: Verify user has exactly the `editor` role (not `administrator`)

#### Menu not displaying
**Cause**: Theme or plugin conflicts
**Fix**: Check for JavaScript errors and plugin conflicts

### Debug Mode
Add this to wp-config.php for debugging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Version History

### v1.0.0
- Initial implementation
- Core access control functionality
- Custom dashboard for editors
- Admin menu customization
- Security measures implementation
