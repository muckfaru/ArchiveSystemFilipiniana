# Dashboard Custom Metadata Display Patch

## Instructions
Add the following code snippet in `views/dashboard.php` in TWO locations:

### Location 1: Search Results Section (around line 214)
### Location 2: Recent Activities Section (around line 360)

Insert this code RIGHT BEFORE the closing `</div>` of the `dashboard-card-info` div (after the keywords/tags section):

```php
<!-- Custom Metadata -->
<?php if (!empty($paper['custom_metadata'])): ?>
    <?= renderCustomMetadata($paper['custom_metadata'], 3) ?>
<?php endif; ?>
```

## Context
This should be inserted after:
```php
                                <?php endif; ?>
                            </div>  <!-- This is the closing div for dashboard-card-info -->
```

And before:
```php
                            <!-- Admin action buttons (shown on hover) -->
```

## Complete Example
```php
                                <?php endif; ?>
                                
                                <!-- Custom Metadata -->
                                <?php if (!empty($paper['custom_metadata'])): ?>
                                    <?= renderCustomMetadata($paper['custom_metadata'], 3) ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Admin action buttons (shown on hover) -->
```
