# WooBoost AI Model Security Update

## Overview
This document outlines the major security and configuration update made to WooBoost's AI model handling system. The update implements strict model validation to prevent unauthorized model usage and ensure only approved AI models are used for content generation.

## Changes Made

### 1. Centralized Model Configuration
- **File**: `includes/core/class-wooboost-openai.php`
- **Change**: Added `ALLOWED_MODELS` constant with approved models only
- **Security**: Prevents model hallucination and unauthorized model usage

#### Approved Models
Only the following models are now permitted:
- `gpt-5-mini` - Fast and efficient model
- `gpt-5-nano` - Ultra-fast and cost-effective (Default)
- `gpt-4o-mini` - Optimized GPT-4 model  
- `gpt-4.1-nano` - Latest GPT-4.1 nano variant

### 2. Model Validation System
Added comprehensive validation methods:
- `get_allowed_models()` - Returns the full model configuration array
- `get_allowed_model_ids()` - Returns array of allowed model IDs
- `get_default_model()` - Returns the default model (gpt-5-nano)
- `is_model_allowed($model_id)` - Validates if a model is in the approved list
- `validate_model($model_id)` - Sanitizes model input with fallback to default

### 3. Backend Security
- **File**: `includes/process/class-wooboost-rest.php`
- **Change**: Added strict model validation in REST API endpoints
- **Security**: All model requests are validated before processing
- **Fallback**: Invalid models automatically fall back to `gpt-5-nano`

### 4. Frontend Security
- **File**: `includes/frontend/editor-ui.js`
- **Change**: Replaced hardcoded model options with dynamic generation from approved list
- **Security**: Frontend cannot request unauthorized models
- **UX**: Model dropdown is generated from centralized configuration

### 5. Default Model Update
- **Old Default**: `gpt-3.5-turbo`
- **New Default**: `gpt-5-nano`
- **Reason**: Cost optimization and security compliance

### 6. API Parameter Compatibility
- **Issue 1**: GPT-5 models require `max_completion_tokens` instead of `max_tokens`
- **Issue 2**: GPT-5 models don't support custom `temperature` values (only default: 1)
- **Issue 3**: GPT-5 models don't support advanced parameters (`top_p`, `frequency_penalty`, `presence_penalty`)
- **Solution**: Automatic parameter selection based on model capabilities
- **Implementation**: 
  - `uses_completion_tokens()` - determines token parameter
  - `supports_temperature()` - determines temperature support
  - `supports_advanced_parameters()` - determines advanced parameter support
- **Result**: No more "Unsupported parameter" errors

## Security Features

### Anti-Hallucination Measures
1. **Whitelist Approach**: Only pre-approved models are accepted
2. **Automatic Rejection**: Invalid models are rejected and logged
3. **Fallback Security**: Always falls back to safe default model
4. **Centralized Control**: Single source of truth for model configuration

### Input Validation
- Server-side validation in PHP
- Client-side validation in JavaScript
- REST API parameter validation
- Automatic sanitization of model inputs

### Logging
- Invalid model requests are logged for security monitoring
- Model validation failures are tracked in browser console
- Helps identify potential security issues or misconfigurations

## Testing
A comprehensive test suite was created and executed to verify:
- ✅ Only approved models are returned by `list_models()`
- ✅ Invalid models are rejected and fall back to default
- ✅ Model validation prevents hallucination
- ✅ Centralized configuration ensures consistency
- ✅ Default model is secure (gpt-5-nano)
- ✅ GPT-5 models use `max_completion_tokens` parameter
- ✅ GPT-4 models use `max_tokens` parameter
- ✅ GPT-5 models do NOT include temperature parameter
- ✅ GPT-5 models do NOT include advanced parameters
- ✅ GPT-4 models include all parameters
- ✅ No API parameter errors occur

## Migration Notes
- **Backward Compatibility**: Existing functionality is preserved
- **Automatic Migration**: Old model references are automatically updated
- **No Database Changes**: All changes are code-level only
- **Zero Downtime**: Changes are immediately effective

## Developer Notes

### Adding New Models
To add a new approved model:
1. Update `ALLOWED_MODELS` constant in `class-wooboost-openai.php`
2. Update `allowedModels` object in `editor-ui.js`
3. Ensure both arrays are synchronized

### Model Format
```php
'model-id' => array(
    'id' => 'model-id',
    'name' => 'Display Name',
    'description' => 'User-friendly description'
)
```

### API Parameter Compatibility
Different model generations use different API parameters and have different capabilities:

**Token Parameters:**
```php
// GPT-5 models (gpt-5-nano, gpt-5-mini)
$request_data['max_completion_tokens'] = $max_tokens_value;

// GPT-4 models (gpt-4o-mini, gpt-4.1-nano)  
$request_data['max_tokens'] = $max_tokens_value;
```

**Temperature Support:**
```php
// Only include temperature for models that support it
if (self::supports_temperature($validated_model)) {
    $request_data['temperature'] = $temperature; // GPT-4 models only
}
// GPT-5 models use default temperature (1) and don't accept custom values
```

**Advanced Parameters:**
```php
// Only include advanced parameters for models that support them
if (self::supports_advanced_parameters($validated_model)) {
    $request_data['top_p'] = 1;                    // GPT-4 models only
    $request_data['frequency_penalty'] = 0;        // GPT-4 models only
    $request_data['presence_penalty'] = 0;         // GPT-4 models only
}
// GPT-5 models don't support these parameters
```

The system automatically selects compatible parameters using capability detection methods.

### Security Guidelines
- Never bypass model validation
- Always use the validation methods provided
- Log suspicious model requests
- Keep approved model list minimal and controlled

## Impact Assessment
- **Security**: ✅ Significantly improved - prevents unauthorized model usage
- **Performance**: ✅ Improved - uses more efficient default model
- **User Experience**: ✅ Maintained - no visible changes to users
- **Maintainability**: ✅ Improved - centralized configuration
- **Cost**: ✅ Reduced - more cost-effective default model

## File Changes Summary
```
includes/core/class-wooboost-openai.php     - Model configuration & validation
includes/process/class-wooboost-rest.php    - REST API validation
includes/frontend/editor-ui.js              - Frontend model handling
```

---
*Last Updated: September 8, 2025*  
*Version: 1.0.0*
