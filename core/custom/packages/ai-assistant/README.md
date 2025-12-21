# AI Assistant for Evolution CMS

A Shopify Sidekick-like AI assistant for Evolution CMS that helps you manage your content through natural language conversations.

## Features

- **Resource Search** - Find pages by keywords, templates, or status
- **Content Editing** - Edit resource fields and TV values through chat
- **TV Management** - Create new Template Variables and bind them to templates
- **Template Editing** - Edit both inline templates and Blade view files
- **Publishing** - Publish and unpublish resources with a simple command
- **SEO Optimization** - Analyze and optimize your pages for search engines
- **Checkpoints** - Automatic rollback points before every change

## Requirements

- Evolution CMS 3.x
- PHP 8.1 or higher
- OpenAI API key or Anthropic API key

## Installation

### Method 1: Via Composer (Recommended)

```bash
composer require evocms/ai-assistant
php artisan package:install ai-assistant
```

### Method 2: Manual Installation

1. Copy the `ai-assistant` folder to `core/custom/packages/`

2. Register the service provider in `core/custom/config/app.php`:
   ```php
   'providers' => [
       // ...
       EvolutionCMS\AiAssistant\AiAssistantServiceProvider::class,
   ],
   ```

3. Run migrations:
   ```bash
   php artisan migrate
   ```

4. Copy assets:
   ```bash
   php artisan vendor:publish --tag=ai-assistant
   ```

5. Install the plugin:
   - Go to Elements → Plugins → New Plugin
   - Name: `AI Assistant`
   - Paste the content from `assets/plugins/ai_assistant.php`
   - Enable events: `OnManagerFrameLoader`, `OnManagerTopPrerender`

## Configuration

Add these environment variables to your `.env` file:

```env
# AI Provider (openai or anthropic)
AI_ASSISTANT_PROVIDER=openai

# OpenAI Configuration
OPENAI_API_KEY=your-openai-api-key
OPENAI_MODEL=gpt-4

# OR Anthropic Configuration
ANTHROPIC_API_KEY=your-anthropic-api-key
ANTHROPIC_MODEL=claude-3-sonnet-20240229

# UI Configuration (optional)
AI_ASSISTANT_POSITION=right
AI_ASSISTANT_DEFAULT_OPEN=false
AI_ASSISTANT_PANEL_WIDTH=400px

# Checkpoint Configuration (optional)
AI_CHECKPOINTS_MAX=10
AI_CHECKPOINTS_CLEANUP_DAYS=30
```

## Usage

### Opening the Assistant

1. Look for the purple icon on the right side of the manager
2. Click it or use `Ctrl/Cmd + Shift + A` keyboard shortcut

### Example Commands

**Search:**
- "Find all published articles"
- "Search for pages with 'contact' in the title"
- "Show unpublished resources"

**Editing:**
- "Update the title of page #5 to 'New Title'"
- "Set the description of 'About Us' page"
- "Change the introtext for resource 10"

**TV Management:**
- "Create a new TV called 'featured_image' of type image"
- "Bind TV 'meta_keywords' to template 'Blog Post'"
- "Show all TV values for page #15"

**Publishing:**
- "Publish page #20"
- "Unpublish all resources with template 'Draft'"

**SEO:**
- "Analyze SEO for the homepage"
- "Optimize SEO for page #5 with keyword 'web design'"
- "Show SEO score for all blog posts"

**Rollback:**
- "Show my recent changes"
- "Rollback the last change"

## API Endpoints

All endpoints require manager authentication.

### Chat
```
POST /ai-assistant/api/chat
Body: { "message": "your message", "context": {} }
```

### Resources
```
GET  /ai-assistant/api/resources/search?keyword=...
GET  /ai-assistant/api/resources/{id}
PUT  /ai-assistant/api/resources/{id}
POST /ai-assistant/api/resources/{id}/publish
POST /ai-assistant/api/resources/{id}/unpublish
GET  /ai-assistant/api/resources/{id}/tv
PUT  /ai-assistant/api/resources/{id}/tv
```

### Templates
```
GET  /ai-assistant/api/templates
GET  /ai-assistant/api/templates/{id}
PUT  /ai-assistant/api/templates/{id}
PUT  /ai-assistant/api/templates/{id}/blade
```

### TV
```
GET  /ai-assistant/api/tv
POST /ai-assistant/api/tv
GET  /ai-assistant/api/tv/{id}
PUT  /ai-assistant/api/tv/{id}
POST /ai-assistant/api/tv/{id}/bind
```

### SEO
```
GET  /ai-assistant/api/seo/analyze/{id}
POST /ai-assistant/api/seo/optimize/{id}
POST /ai-assistant/api/seo/suggestions/{id}
```

### Checkpoints
```
GET  /ai-assistant/api/checkpoints
POST /ai-assistant/api/checkpoints/{id}/rollback
POST /ai-assistant/api/checkpoints/rollback-session
```

## Security

- All API endpoints require valid manager session
- Actions respect Evolution CMS permissions
- Checkpoints are created automatically before any modification
- No direct database queries - all operations go through Evolution CMS models

## Customization

### Custom System Prompt

Edit `config/ai-assistant.php` to customize the AI's behavior:

```php
'system_prompt' => 'Your custom instructions here...',
```

### Disable Features

```php
'actions' => [
    'search_resources' => true,
    'edit_resources' => true,
    'create_resources' => true,
    'delete_resources' => false, // Disabled for safety
    // ...
],
```

## Troubleshooting

### Assistant doesn't appear
1. Check if the plugin is enabled
2. Verify events are assigned: `OnManagerFrameLoader`, `OnManagerTopPrerender`
3. Clear browser cache

### "Not configured" message
1. Verify API key is set in `.env`
2. Check the provider setting matches your API key
3. Clear Evolution CMS cache

### Actions don't work
1. Check user permissions in Evolution CMS
2. Verify the target resources/templates exist
3. Check browser console for errors

## License

MIT License

## Support

- GitHub Issues: [Report a bug](https://github.com/evolution-cms/ai-assistant/issues)
- Documentation: [https://docs.evo.im](https://docs.evo.im)
