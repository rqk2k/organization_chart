# Organization Chart Module for Drupal 10

A comprehensive organizational chart module for Drupal 10 that allows you to create and display hierarchical team structures with a visual drag-and-drop builder interface.

## Features

### 🎨 Visual Chart Builder

- Intuitive drag-and-drop interface for creating organizational charts
- Real-time preview with connection lines between elements
- Grid snapping for precise element positioning
- Zoom and pan functionality for large charts
- Keyboard shortcuts for efficient editing

### 🎯 Flexible Display Options

- Multiple themes with customizable styling
- Responsive design that adapts to different screen sizes
- Zoom controls, fullscreen mode, and popup details
- Horizontal scrolling for wide charts
- Mobile-friendly touch navigation

### 🛠 Powerful Administration

- Unlimited charts and themes
- Element duplication and bulk operations
- Image upload with preview
- Custom CSS and JavaScript support
- Auto-save functionality with change tracking

### 🔗 Multiple Integration Methods

- **Blocks**: Add charts to any block region
- **Shortcodes**: Embed charts using `[org_chart chart_id=1]` syntax
- **Service API**: Programmatic chart rendering
- **Filter integration**: Process shortcodes in content

### ⚡ Performance & Accessibility

- Built-in caching for improved performance
- Lazy loading for images
- Full keyboard navigation support
- Screen reader compatible with ARIA labels
- Print-friendly CSS

## Installation

1. Download and extract the module to your `modules/custom/` directory
2. Enable the module via Drush or the admin interface:
   ```bash
   drush en organization_chart
   ```
3. Set up permissions at `/admin/people/permissions`
4. Configure global settings at `/admin/config/content/organization-chart`

## Quick Start Guide

### Creating Your First Chart

1. Navigate to **Structure > Organization Charts** (`/admin/structure/organization-chart`)
2. Click **"Add new organization chart"**
3. Enter a name and description for your chart
4. Click **"Create Chart"** then **"Open Builder"**
5. Use the visual builder to:
   - Add elements by hovering over existing elements and clicking the **+** button
   - Edit element properties by clicking on them
   - Drag elements to reposition them
   - Upload images for team members
6. Save your chart when complete

### Creating Themes

1. Go to **Structure > Organization Charts > Chart Themes**
2. Click **"Add new theme"**
3. Configure styling options:
   - **General Settings**: Zoom, responsive behavior, popups
   - **Line Style**: Connection line appearance
   - **Item Style**: Element colors, fonts, sizes, borders
4. Save your theme

### Displaying Charts

#### Using Blocks

1. Go to **Structure > Block layout**
2. Click **"Place block"** in your desired region
3. Find and configure the **"Organization Chart"** block
4. Select your chart and theme
5. Configure display options
6. Save the block

#### Using Shortcodes

Add shortcodes directly in your content:

```
[org_chart chart_id=1]
[org_chart chart_id=1 theme_id=2]
[org_chart chart_id=1 show_title=0 show_controls=0]
[org_chart chart_id=1 max_width=800px max_height=600px]
```

**Available Parameters:**

- `chart_id` (required): The ID of the chart to display
- `theme_id` (optional): Specific theme to use
- `show_title` (optional): Show chart title (1 or 0)
- `show_controls` (optional): Show zoom controls (1 or 0)
- `enable_fullscreen` (optional): Enable fullscreen button (1 or 0)
- `max_width` (optional): Maximum width (e.g., 100%, 800px)
- `max_height` (optional): Maximum height (e.g., 600px, auto)

#### Programmatic Usage

```php
// Get the service
$chartService = \Drupal::service('organization_chart.service');

// Render a chart
$render_array = $chartService->renderChart(1, 2, [
  'show_title' => TRUE,
  'show_controls' => TRUE,
  'max_width' => '100%',
]);

// Get chart hierarchy
$hierarchy = $chartService->getChartHierarchy(1);

// Duplicate a chart
$new_chart_id = $chartService->duplicateChart(1, 'New Chart Name');
```

## Module Structure

```
organization_chart/
├── config/
│   └── schema/
│       └── organization_chart.schema.yml
├── css/
│   ├── organization-chart-admin.css
│   ├── organization-chart-builder.css
│   └── organization-chart-display.css
├── js/
│   ├── organization-chart-admin.js
│   ├── organization-chart-builder.js
│   └── organization-chart-display.js
├── src/
│   ├── Controller/
│   │   ├── OrganizationChartAjaxController.php
│   │   └── OrganizationChartController.php
│   ├── Form/
│   │   ├── OrganizationChartDeleteForm.php
│   │   ├── OrganizationChartForm.php
│   │   ├── OrganizationChartSettingsForm.php
│   │   └── OrganizationChartThemeForm.php
│   ├── Plugin/
│   │   ├── Block/
│   │   │   └── OrganizationChartBlock.php
│   │   └── Filter/
│   │       └── OrganizationChartFilter.php
│   └── OrganizationChartService.php
├── templates/
│   ├── organization-chart-builder.html.twig
│   ├── organization-chart-display.html.twig
│   └── organization-chart-element.html.twig
├── organization_chart.info.yml
├── organization_chart.install
├── organization_chart.libraries.yml
├── organization_chart.links.menu.yml
├── organization_chart.module
├── organization_chart.permissions.yml
├── organization_chart.routing.yml
├── organization_chart.services.yml
└── README.md
```

## Database Schema

The module creates three main tables:

### `organization_charts`

Stores chart metadata including name, description, and creation timestamps.

### `organization_chart_themes`

Stores theme configurations with JSON-encoded settings for styling options.

### `organization_chart_elements`

Stores individual chart elements with hierarchical relationships, positioning data, and content.

## Permissions

- **Administer organization charts**: Create, edit, and delete charts and themes
- **View organization charts**: View charts on the frontend

## Theming

### Template Files

- `organization-chart-display.html.twig`: Main chart display template
- `organization-chart-element.html.twig`: Individual element template
- `organization-chart-builder.html.twig`: Chart builder interface template

### CSS Classes

- `.organization-chart`: Main chart container
- `.org-chart-element`: Individual chart elements
- `.org-chart-level`: Hierarchical levels
- `.org-chart-lines`: Connection lines SVG
- `.org-chart-popup`: Element detail popups

### Theme Hooks

```php
/**
 * Implements hook_theme_suggestions_HOOK().
 */
function MYTHEME_theme_suggestions_organization_chart_display(array $variables) {
  $suggestions = [];
  if ($chart = $variables['chart']) {
    $suggestions[] = 'organization_chart_display__' . $chart->id;
  }
  return $suggestions;
}
```

## Configuration Options

### Global Settings (`/admin/config/content/organization-chart`)

- Default placeholder image for elements without photos
- Caching and performance options
- User permissions and chart limits
- Default theme selection
- Animation settings
- Custom CSS and JavaScript

### Theme Settings

- **General**: Responsive behavior, zoom controls, popups
- **Line Style**: Connection line colors, widths, and styles
- **Item Style**: Element backgrounds, borders, text, and image styling

## Browser Compatibility

- **Modern Browsers**: Chrome 60+, Firefox 55+, Safari 12+, Edge 79+
- **Mobile**: iOS Safari 12+, Chrome Mobile 60+
- **Features**: CSS Grid, Flexbox, SVG, HTML5 File API, Touch Events

## Performance Considerations

- **Caching**: Enable chart caching for production sites
- **Lazy Loading**: Use lazy loading for image-heavy charts
- **Large Charts**: Use zoom mode instead of responsive for charts with 50+ elements
- **Database**: Consider indexing for sites with hundreds of charts

## Troubleshooting

### Common Issues

**Charts not displaying:**

- Check permissions for viewing organization charts
- Verify the chart ID exists and is not empty
- Clear Drupal cache

**Builder interface not working:**

- Ensure JavaScript is enabled
- Check browser console for errors
- Verify jQuery UI libraries are loaded

**Images not uploading:**

- Check file permissions in `public://organization_chart/`
- Verify file upload limits in PHP configuration
- Ensure image file extensions are allowed

**Performance issues:**

- Enable caching in module settings
- Reduce chart complexity or split into multiple charts
- Optimize uploaded images for web use

### Debug Mode

Enable verbose logging by adding to `settings.php`:

```php
$config['organization_chart.settings']['debug_mode'] = TRUE;
```

## Contributing

This module was converted from the WordPress Organization Chart plugin by WpDevArt.

### Development Setup

1. Clone the repository
2. Install Drupal 10 development environment
3. Enable the module with development dependencies
4. Run coding standards checks:
   ```bash
   phpcs --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme,css,info,txt,md,yml .
   ```

## License

This project is licensed under the GPL-2.0+ License - see the original WordPress plugin license.

## Support

- **Issue Queue**: Report bugs and feature requests on the project page
- **Documentation**: Visit `/admin/help/organization_chart` for built-in help
- **Community**: Join the Drupal community for support and discussions

## Changelog

### 1.0.0

- Initial release
- Complete WordPress plugin conversion
- Visual chart builder with drag-and-drop
- Multiple display methods (blocks, shortcodes, API)
- Comprehensive theming system
- Mobile-responsive design
- Accessibility features
- Performance optimizations
