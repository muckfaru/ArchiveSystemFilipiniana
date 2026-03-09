# Design Document: Metadata Display Customization

## Overview

The Metadata Display Customization feature enables administrators to control the visibility and display order of custom metadata fields in two distinct contexts: file cards (basic view) and preview modals (detailed view). This system provides granular control over what information users see and in what sequence, allowing administrators to optimize the user experience by showing summary information on cards and detailed information in modals.

### Design Goals

- Provide independent control over field visibility for file cards and preview modals
- Enable flexible ordering of metadata fields in each display context
- Maintain consistent display logic across admin dashboard and public pages
- Ensure backward compatibility with existing custom metadata fields
- Optimize query performance through caching and indexing
- Support sensible defaults when configuration is not explicitly set

### System Context

This feature extends the existing Custom Metadata Form Builder System by adding a display configuration layer. The system integrates with:
- Custom metadata fields (custom_metadata_fields table)
- Custom metadata values (custom_metadata_values table)
- Admin dashboard (dashboard.php) file cards and preview modals
- Public page (public.php) file cards and preview modals
- Browse page (browse.php) file cards and preview modals

The display customization system sits between the data layer and presentation layer, filtering and ordering metadata fields before they are rendered to users.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Presentation Layer                          │
├─────────────────────────────────────────────────────────────────┤
│  Dashboard      │  Public Page    │  Browse Page   │  Config UI │
│  (dashboard.php)│  (public.php)   │  (browse.php)  │  (metadata-│
│                 │                 │                │   display.  │
│                 │                 │                │   php)      │
└────────┬────────┴────────┬────────┴────────┬───────┴────────┬───┘
         │                 │                 │                │
         │                 │                 │                │
┌────────▼─────────────────▼─────────────────▼────────────────▼───┐
│                   Display Configuration Layer                    │
├──────────────────────────────────────────────────────────────────┤
│  getVisibleFields()  │  getFieldOrder()  │  applyDisplayConfig()│
│  (filter by context) │  (sort by order)  │  (cache & optimize)  │
└────────┬─────────────┴────────┬──────────┴────────┬─────────────┘
         │                      │                   │
         │                      │                   │
┌────────▼──────────────────────▼───────────────────▼──────────────┐
│                         Data Layer                                │
├───────────────────────────────────────────────────────────────────┤
│  custom_metadata_    │  custom_metadata_  │  metadata_display_   │
│  fields              │  values            │  config (new)        │
└───────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

**Presentation Layer:**
- Dashboard/Public/Browse Pages: Render file cards and preview modals using filtered and ordered metadata
- Configuration UI: Administrative interface for managing display settings

**Display Configuration Layer:**
- Filter fields based on visibility settings for each context (file card vs preview modal)
- Sort fields according to display order settings
- Apply default behavior when configuration is missing
- Cache configuration for performance optimization

**Data Layer:**
- custom_metadata_fields: Stores field definitions
- custom_metadata_values: Stores actual metadata values for files
- metadata_display_config: Stores display configuration (visibility and order) per field and context

### Data Flow

#### Display Configuration Retrieval Flow

```
1. User loads page (dashboard/public/browse)
   ↓
2. PHP queries metadata_display_config with custom_metadata_fields (LEFT JOIN)
   ↓
3. PHP applies default behavior for fields without configuration
   ↓
4. PHP caches configuration in memory for page request duration
   ↓
5. For each file card:
   - Filter fields where show_on_card = 1 (or default)
   - Sort by card_display_order (or default)
   - Render visible fields with values
   ↓
6. For each preview modal:
   - Filter fields where show_in_modal = 1 (or default)
   - Sort by modal_display_order (or default)
   - Render visible fields with values
```

#### Configuration Update Flow

```
1. Admin navigates to Display Configuration UI
   ↓
2. PHP loads all custom_metadata_fields with current display config
   ↓
3. Admin modifies visibility/order settings
   ↓
4. JavaScript validates input client-side
   ↓
5. JavaScript POSTs to backend/api/metadata-display-config.php
   ↓
6. PHP validates all inputs server-side
   ↓
7. PHP begins database transaction
   ↓
8. PHP updates/inserts metadata_display_config records
   ↓
9. PHP commits transaction
   ↓
10. PHP logs activity
    ↓
11. PHP returns success response
    ↓
12. JavaScript updates UI without page refresh
```

## Components and Interfaces

### Database Schema

**New Table: metadata_display_config**

```sql
CREATE TABLE metadata_display_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    field_id INT NOT NULL,
    show_on_card TINYINT(1) DEFAULT 1 COMMENT 'Show field on file cards',
    show_in_modal TINYINT(1) DEFAULT 1 COMMENT 'Show field in preview modals',
    card_display_order INT DEFAULT 0 COMMENT 'Display order on file cards',
    modal_display_order INT DEFAULT 0 COMMENT 'Display order in preview modals',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (field_id) REFERENCES custom_metadata_fields(id) ON DELETE CASCADE,
    UNIQUE KEY unique_field (field_id),
    INDEX idx_card_visibility (show_on_card, card_display_order),
    INDEX idx_modal_visibility (show_in_modal, modal_display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Design Rationale:**
- Separate columns for card and modal visibility allow independent control
- Separate order columns enable different sequences in each context
- Foreign key with CASCADE ensures cleanup when fields are deleted
- Unique constraint prevents duplicate configurations for same field
- Composite indexes optimize the most common queries (filter by visibility + sort by order)

### Backend API

**File:** `backend/api/metadata-display-config.php`

**Endpoints:**

```php
// GET /backend/api/metadata-display-config.php?action=list
// Returns all fields with their display configuration
{
    "success": true,
    "fields": [
        {
            "field_id": 1,
            "field_name": "author",
            "field_label": "Author",
            "show_on_card": 1,
            "show_in_modal": 1,
            "card_display_order": 1,
            "modal_display_order": 1
        }
    ]
}

// POST /backend/api/metadata-display-config.php?action=update
// Updates display configuration for one or more fields
{
    "configurations": [
        {
            "field_id": 1,
            "show_on_card": 1,
            "show_in_modal": 1,
            "card_display_order": 1,
            "modal_display_order": 2
        }
    ]
}

// POST /backend/api/metadata-display-config.php?action=reset
// Resets configuration to defaults
{
    "field_id": 1  // Optional: if omitted, resets all fields
}
```

**Validation Rules:**
- field_id must exist in custom_metadata_fields
- show_on_card and show_in_modal must be 0 or 1
- card_display_order and modal_display_order must be non-negative integers
- At least one field must have show_in_modal = 1 (business rule)

### Helper Functions

**File:** `backend/core/functions.php`

**New Functions:**

```php
/**
 * Get display configuration for all custom metadata fields
 * Applies default behavior for fields without explicit configuration
 * 
 * @param PDO $pdo Database connection
 * @param string $context 'card' or 'modal'
 * @return array Filtered and ordered fields
 */
function getDisplayConfig($pdo, $context = 'both') {
    // Implementation details in code section
}

/**
 * Get visible custom metadata fields for a specific context
 * 
 * @param PDO $pdo Database connection
 * @param string $context 'card' or 'modal'
 * @return array Visible fields in display order
 */
function getVisibleFields($pdo, $context) {
    // Implementation details in code section
}

/**
 * Get custom metadata values for a file with display configuration applied
 * 
 * @param PDO $pdo Database connection
 * @param int $fileId File ID
 * @param string $context 'card' or 'modal'
 * @return array Field-value pairs for visible fields only
 */
function getFileMetadataForDisplay($pdo, $fileId, $context) {
    // Implementation details in code section
}
```

### Configuration UI

**File:** `pages/metadata-display.php`

**Controller Logic:**

```php
<?php
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    redirect('dashboard.php?error=' . urlencode('Access denied'));
}

// Get all custom fields with display configuration
$stmt = $pdo->query("
    SELECT 
        cmf.id,
        cmf.field_name,
        cmf.field_label,
        cmf.field_type,
        COALESCE(mdc.show_on_card, 1) as show_on_card,
        COALESCE(mdc.show_in_modal, 1) as show_in_modal,
        COALESCE(mdc.card_display_order, cmf.id) as card_display_order,
        COALESCE(mdc.modal_display_order, cmf.id) as modal_display_order
    FROM custom_metadata_fields cmf
    LEFT JOIN metadata_display_config mdc ON cmf.id = mdc.field_id
    WHERE cmf.is_enabled = 1
    ORDER BY cmf.field_name ASC
");
$fields = $stmt->fetchAll();

// Load view
include __DIR__ . '/../views/metadata-display.php';
```

**View Structure:** `views/metadata-display.php`

The view will provide:
- Table showing all custom metadata fields
- Toggle switches for card visibility
- Toggle switches for modal visibility
- Number inputs for card display order
- Number inputs for modal display order
- Bulk actions (reset all, save all)
- Real-time preview of how fields will appear

**JavaScript:** `assets/js/pages/metadata-display.js`

Key functionality:
- Real-time validation of display order values
- Drag-and-drop reordering interface
- Live preview of field arrangement
- Bulk update operations
- Confirmation dialogs for destructive actions


### Display Integration

**Modified Files:**
- `views/dashboard.php` - File cards and preview modal
- `views/public.php` - File cards and preview modal
- `views/browse.php` - File cards and preview modal

**Integration Pattern:**

```php
// In dashboard.php, public.php, browse.php
// Load visible fields for cards (done once per page load)
$cardFields = getVisibleFields($pdo, 'card');

// For each file in the loop
foreach ($files as $file) {
    $cardMetadata = getFileMetadataForDisplay($pdo, $file['id'], 'card');
    
    // Render file card with $cardMetadata
    // Only fields in $cardMetadata will be displayed
    // They are already in the correct order
}

// In preview modal JavaScript
// When modal opens, fetch modal-specific metadata
fetch(`/backend/api/file-metadata.php?id=${fileId}&context=modal`)
    .then(response => response.json())
    .then(data => {
        // Render metadata fields in modal
        // Fields are pre-filtered and pre-ordered by backend
    });
```

## Data Models

### metadata_display_config

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| field_id | INT | NOT NULL, FOREIGN KEY, UNIQUE | Reference to custom_metadata_fields |
| show_on_card | TINYINT(1) | DEFAULT 1 | Whether field appears on file cards |
| show_in_modal | TINYINT(1) | DEFAULT 1 | Whether field appears in preview modals |
| card_display_order | INT | DEFAULT 0 | Display sequence on file cards (ascending) |
| modal_display_order | INT | DEFAULT 0 | Display sequence in preview modals (ascending) |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation timestamp |
| updated_at | DATETIME | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last modification timestamp |

**Relationships:**
- One-to-one with custom_metadata_fields (field_id)
- Cascade delete when parent field is deleted

**Default Behavior:**
When a record does not exist for a field_id:
- show_on_card = 1 (visible)
- show_in_modal = 1 (visible)
- card_display_order = field's id from custom_metadata_fields
- modal_display_order = field's id from custom_metadata_fields

### Display Configuration DTO

```php
class DisplayConfig {
    public int $fieldId;
    public string $fieldName;
    public string $fieldLabel;
    public string $fieldType;
    public bool $showOnCard;
    public bool $showInModal;
    public int $cardDisplayOrder;
    public int $modalDisplayOrder;
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Configuration Persistence Round Trip

*For any* valid display configuration (field_id, visibility settings, and display orders), saving the configuration and then retrieving it should return the same values.

**Validates: Requirements 1.1, 1.2, 1.3, 1.4, 8.2**

### Property 2: Referential Integrity Enforcement

*For any* attempt to create a display configuration with a field_id that does not exist in custom_metadata_fields, the system should reject the operation.

**Validates: Requirements 1.6**

### Property 3: Default Behavior Application

*For any* custom metadata field without an explicit display configuration, the system should display it on both file cards and preview modals, ordered by the field's creation order.

**Validates: Requirements 1.5, 7.1, 7.2, 7.3**

### Property 4: Access Control Enforcement

*For any* user who is not an administrator, attempting to access the configuration UI should be denied.

**Validates: Requirements 2.1**

### Property 5: Complete Field Display in UI

*For any* set of enabled custom metadata fields, the configuration UI should display all of them with their current settings.

**Validates: Requirements 2.2, 2.3**

### Property 6: Non-Negative Order Validation

*For any* display order value submitted by an administrator, if the value is negative or non-numeric, the system should reject it with an appropriate error message.

**Validates: Requirements 3.1, 3.4, 12.1, 12.2**

### Property 7: Alphabetical Tie-Breaking

*For any* set of custom metadata fields with identical display_order values, the system should sort them alphabetically by field_name.

**Validates: Requirements 3.2**

### Property 8: Context Independence

*For any* custom metadata field, setting different display orders for file cards and preview modals should not cause interference—each context should maintain its own independent ordering.

**Validates: Requirements 3.3**

### Property 9: Ascending Order Application

*For any* set of custom metadata fields with configured display orders, the system should render them in ascending numeric order.

**Validates: Requirements 3.5**

### Property 10: Visibility Filtering

*For any* custom metadata field with visibility disabled for a specific context (card or modal), that field should not appear in that context on any page (admin dashboard, public page, or browse page).

**Validates: Requirements 4.1, 4.2, 4.3, 4.4, 5.1, 5.3, 6.1, 6.3**

### Property 11: Visibility Combination Support

*For any* custom metadata field, the system should support all four visibility combinations: visible on card only, visible in modal only, visible in both, or visible in neither.

**Validates: Requirements 4.5**

### Property 12: Display Order Consistency

*For any* set of custom metadata fields with configured display orders, the fields should appear in the same order on both admin dashboard and public pages for the same context (card or modal).

**Validates: Requirements 5.2, 5.4, 6.2, 6.4, 6.5**

### Property 13: Field Label Source

*For any* custom metadata field displayed on a file card or preview modal, the label shown should match the field_label value from the custom_metadata_fields table.

**Validates: Requirements 11.1, 11.2**

### Property 14: Empty Value Omission

*For any* file and custom metadata field, if the file has no value for that field, the field should not appear in the display (rather than showing an empty value).

**Validates: Requirements 11.3**

### Property 15: Format Consistency

*For any* custom metadata field displayed in both file cards and preview modals, the label-value pair format should be consistent across both contexts.

**Validates: Requirements 11.4**

### Property 16: Validation Before Persistence

*For any* configuration submission, if validation fails, no changes should be persisted to the database.

**Validates: Requirements 8.1**

### Property 17: Validation Error Specificity

*For any* configuration submission with invalid values, the error message should specifically identify which fields have invalid values.

**Validates: Requirements 8.3**

### Property 18: Success Confirmation

*For any* successful configuration save operation, the system should display a confirmation message to the administrator.

**Validates: Requirements 8.5, 10.5**

### Property 19: Single Configuration Query Per Page

*For any* page rendering multiple file cards, the system should retrieve display configuration exactly once per page load, not once per file.

**Validates: Requirements 9.4**

### Property 20: Configuration Caching

*For any* page request, display configuration should be loaded once and reused for all files on that page.

**Validates: Requirements 9.5**

### Property 21: Reset to Defaults

*For any* custom metadata field with a configured display setting, resetting that field should restore it to default behavior (visible in both contexts, ordered by creation).

**Validates: Requirements 10.3**

### Property 22: At Least One Modal Field Required

*For any* configuration submission, if all fields have show_in_modal disabled, the system should reject the submission with an error message.

**Validates: Requirements 12.3**

### Property 23: Invalid Data Submission Prevention

*For any* configuration form with incomplete or invalid data, the submit action should be blocked until all data is valid.

**Validates: Requirements 12.5**

## Error Handling

### Validation Errors

**Client-Side Validation:**
- Display order must be non-negative integer
- At least one field must be visible in modal
- All required fields must be filled

**Server-Side Validation:**
- Verify field_id exists in custom_metadata_fields
- Validate data types (boolean for visibility, integer for order)
- Enforce business rules (at least one modal field)
- Check user permissions

**Error Response Format:**
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "field_id_5": {
            "card_display_order": "Must be a non-negative integer"
        }
    }
}
```

### Database Errors

**Transaction Handling:**
- All configuration updates wrapped in transactions
- Rollback on any error
- Log errors for debugging
- Return user-friendly error messages

**Common Scenarios:**
- Foreign key constraint violation (invalid field_id)
- Unique constraint violation (duplicate configuration)
- Connection timeout
- Deadlock

**Error Response:**
```json
{
    "success": false,
    "message": "Failed to save configuration. Please try again.",
    "technical_details": "Foreign key constraint violation" // Only in dev mode
}
```

### Missing Configuration

**Graceful Degradation:**
- If metadata_display_config table doesn't exist, fall back to showing all fields
- If a field has no configuration record, apply defaults
- If query fails, log error and show all fields

### Edge Cases

**No Custom Fields Exist:**
- Display message: "No custom metadata fields have been created yet. Create fields first in the Field Manager."
- Disable save button
- Provide link to field manager

**All Fields Hidden on Cards:**
- Display message on file cards: "No metadata configured for display"
- Ensure at least one field visible in modal (enforced by validation)

**Database Migration Incomplete:**
- Check for table existence before queries
- Provide clear error message if table missing
- Log migration status

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests for comprehensive coverage:

**Unit Tests** focus on:
- Specific examples of configuration scenarios
- Edge cases (no fields, all hidden, etc.)
- Integration points between components
- Error conditions and validation

**Property Tests** focus on:
- Universal properties that hold for all inputs
- Comprehensive input coverage through randomization
- Invariants that must always be true
- Round-trip properties (save/load, reset/default)

### Property-Based Testing

**Framework:** PHPUnit with [php-quickcheck](https://github.com/steos/php-quickcheck) or similar

**Configuration:**
- Minimum 100 iterations per property test
- Each test tagged with feature name and property number
- Tag format: `@group metadata-display-customization @property {number}`

**Property Test Examples:**

```php
/**
 * @group metadata-display-customization
 * @property 1
 * Feature: metadata-display-customization, Property 1: Configuration Persistence Round Trip
 */
public function testConfigurationPersistenceRoundTrip() {
    // Generate random valid configuration
    // Save configuration
    // Retrieve configuration
    // Assert retrieved matches saved
}

/**
 * @group metadata-display-customization
 * @property 10
 * Feature: metadata-display-customization, Property 10: Visibility Filtering
 */
public function testVisibilityFiltering() {
    // Generate random fields with random visibility settings
    // For each context (card/modal)
    // Assert only fields with visibility=true appear
}
```

### Unit Testing

**Test Categories:**

1. **API Endpoint Tests**
   - Test each endpoint with valid inputs
   - Test with invalid inputs (wrong types, missing fields)
   - Test authentication and authorization
   - Test error responses

2. **Helper Function Tests**
   - getDisplayConfig() with various scenarios
   - getVisibleFields() for each context
   - getFileMetadataForDisplay() with files having different metadata

3. **UI Integration Tests**
   - Configuration UI loads correctly
   - Form validation works
   - Save operation updates UI
   - Reset operation works

4. **Display Integration Tests**
   - File cards show correct fields in correct order
   - Preview modals show correct fields in correct order
   - Both admin and public pages use same logic

5. **Edge Case Tests**
   - No custom fields exist
   - All fields hidden on cards
   - All fields have same display order
   - Field deleted while configuration exists

**Test Data Setup:**
- Create test database with sample fields
- Create sample display configurations
- Create sample files with metadata values
- Clean up after each test

### Performance Testing

**Query Performance:**
- Measure query execution time for getDisplayConfig()
- Verify single query per page load
- Test with varying numbers of fields (10, 50, 100)
- Verify index usage with EXPLAIN

**Caching Verification:**
- Count database queries during page render
- Verify configuration loaded once
- Test cache invalidation on update

**Load Testing:**
- Simulate multiple concurrent users
- Measure response times under load
- Verify no performance degradation

### Migration Testing

**Backward Compatibility:**
- Test with existing custom metadata fields
- Verify default behavior applied correctly
- Ensure no data loss
- Test rollback procedure

**Migration Script Tests:**
- Test on clean database
- Test on database with existing data
- Test idempotency (running twice)
- Test rollback script


## Implementation Details

### Database Migration

**File:** `backend/migrations/004_create_metadata_display_config.php`

```php
<?php
require_once __DIR__ . '/../core/config.php';

function runMigration($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Check if table already exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'metadata_display_config'");
        if ($stmt->rowCount() > 0) {
            echo "Migration already applied.\n";
            $pdo->rollBack();
            return;
        }
        
        // Create metadata_display_config table
        $pdo->exec("
            CREATE TABLE metadata_display_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                field_id INT NOT NULL,
                show_on_card TINYINT(1) DEFAULT 1 COMMENT 'Show field on file cards',
                show_in_modal TINYINT(1) DEFAULT 1 COMMENT 'Show field in preview modals',
                card_display_order INT DEFAULT 0 COMMENT 'Display order on file cards',
                modal_display_order INT DEFAULT 0 COMMENT 'Display order in preview modals',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (field_id) REFERENCES custom_metadata_fields(id) ON DELETE CASCADE,
                UNIQUE KEY unique_field (field_id),
                INDEX idx_card_visibility (show_on_card, card_display_order),
                INDEX idx_modal_visibility (show_in_modal, modal_display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create default configurations for existing fields
        $pdo->exec("
            INSERT INTO metadata_display_config (field_id, show_on_card, show_in_modal, card_display_order, modal_display_order)
            SELECT id, 1, 1, id, id
            FROM custom_metadata_fields
            WHERE is_enabled = 1
        ");
        
        $pdo->commit();
        echo "Migration completed successfully.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Migration failed: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli') {
    runMigration($pdo);
}
```

### Helper Functions Implementation

**File:** `backend/core/functions.php` (additions)

```php
/**
 * Get display configuration for all custom metadata fields
 * Applies default behavior for fields without explicit configuration
 * 
 * @param PDO $pdo Database connection
 * @param string $context 'card', 'modal', or 'both'
 * @return array Filtered and ordered fields
 */
function getDisplayConfig($pdo, $context = 'both') {
    static $cache = [];
    
    // Return cached result if available
    $cacheKey = "display_config_{$context}";
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    $query = "
        SELECT 
            cmf.id as field_id,
            cmf.field_name,
            cmf.field_label,
            cmf.field_type,
            COALESCE(mdc.show_on_card, 1) as show_on_card,
            COALESCE(mdc.show_in_modal, 1) as show_in_modal,
            COALESCE(mdc.card_display_order, cmf.id) as card_display_order,
            COALESCE(mdc.modal_display_order, cmf.id) as modal_display_order
        FROM custom_metadata_fields cmf
        LEFT JOIN metadata_display_config mdc ON cmf.id = mdc.field_id
        WHERE cmf.is_enabled = 1
    ";
    
    if ($context === 'card') {
        $query .= " AND COALESCE(mdc.show_on_card, 1) = 1 ORDER BY card_display_order ASC, cmf.field_name ASC";
    } elseif ($context === 'modal') {
        $query .= " AND COALESCE(mdc.show_in_modal, 1) = 1 ORDER BY modal_display_order ASC, cmf.field_name ASC";
    } else {
        $query .= " ORDER BY cmf.field_name ASC";
    }
    
    $stmt = $pdo->query($query);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cache result
    $cache[$cacheKey] = $result;
    
    return $result;
}

/**
 * Get visible custom metadata fields for a specific context
 * 
 * @param PDO $pdo Database connection
 * @param string $context 'card' or 'modal'
 * @return array Visible fields in display order
 */
function getVisibleFields($pdo, $context) {
    if (!in_array($context, ['card', 'modal'])) {
        throw new InvalidArgumentException("Context must be 'card' or 'modal'");
    }
    
    return getDisplayConfig($pdo, $context);
}

/**
 * Get custom metadata values for a file with display configuration applied
 * 
 * @param PDO $pdo Database connection
 * @param int $fileId File ID
 * @param string $context 'card' or 'modal'
 * @return array Field-value pairs for visible fields only, in display order
 */
function getFileMetadataForDisplay($pdo, $fileId, $context) {
    // Get visible fields for context
    $visibleFields = getVisibleFields($pdo, $context);
    
    if (empty($visibleFields)) {
        return [];
    }
    
    // Get field IDs
    $fieldIds = array_column($visibleFields, 'field_id');
    $placeholders = implode(',', array_fill(0, count($fieldIds), '?'));
    
    // Get values for this file
    $stmt = $pdo->prepare("
        SELECT field_id, field_value
        FROM custom_metadata_values
        WHERE file_id = ? AND field_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$fileId], $fieldIds));
    $values = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Combine fields with values, maintaining order
    $result = [];
    foreach ($visibleFields as $field) {
        $fieldId = $field['field_id'];
        
        // Only include fields that have values
        if (isset($values[$fieldId]) && $values[$fieldId] !== null && $values[$fieldId] !== '') {
            $result[] = [
                'field_id' => $fieldId,
                'field_name' => $field['field_name'],
                'field_label' => $field['field_label'],
                'field_type' => $field['field_type'],
                'field_value' => $values[$fieldId]
            ];
        }
    }
    
    return $result;
}

/**
 * Validate display configuration data
 * 
 * @param array $config Configuration data
 * @return array Array of validation errors (empty if valid)
 */
function validateDisplayConfig($config) {
    $errors = [];
    
    if (!isset($config['field_id']) || !is_numeric($config['field_id'])) {
        $errors['field_id'] = 'Field ID must be a valid number';
    }
    
    if (isset($config['show_on_card']) && !in_array($config['show_on_card'], [0, 1, '0', '1', true, false])) {
        $errors['show_on_card'] = 'Show on card must be 0 or 1';
    }
    
    if (isset($config['show_in_modal']) && !in_array($config['show_in_modal'], [0, 1, '0', '1', true, false])) {
        $errors['show_in_modal'] = 'Show in modal must be 0 or 1';
    }
    
    if (isset($config['card_display_order'])) {
        if (!is_numeric($config['card_display_order']) || $config['card_display_order'] < 0) {
            $errors['card_display_order'] = 'Card display order must be a non-negative integer';
        }
    }
    
    if (isset($config['modal_display_order'])) {
        if (!is_numeric($config['modal_display_order']) || $config['modal_display_order'] < 0) {
            $errors['modal_display_order'] = 'Modal display order must be a non-negative integer';
        }
    }
    
    return $errors;
}
```

### API Implementation

**File:** `backend/api/metadata-display-config.php`

```php
<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            listDisplayConfig($pdo);
            break;
        case 'update':
            updateDisplayConfig($pdo, $currentUser);
            break;
        case 'reset':
            resetDisplayConfig($pdo, $currentUser);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function listDisplayConfig($pdo) {
    $fields = getDisplayConfig($pdo, 'both');
    echo json_encode(['success' => true, 'fields' => $fields]);
}

function updateDisplayConfig($pdo, $currentUser) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['configurations']) || !is_array($data['configurations'])) {
        throw new Exception('Invalid request format');
    }
    
    $configurations = $data['configurations'];
    
    // Validate all configurations first
    $allErrors = [];
    foreach ($configurations as $index => $config) {
        $errors = validateDisplayConfig($config);
        if (!empty($errors)) {
            $allErrors["config_$index"] = $errors;
        }
    }
    
    if (!empty($allErrors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $allErrors
        ]);
        return;
    }
    
    // Check that at least one field has show_in_modal = 1
    $hasModalField = false;
    foreach ($configurations as $config) {
        if (isset($config['show_in_modal']) && $config['show_in_modal'] == 1) {
            $hasModalField = true;
            break;
        }
    }
    
    if (!$hasModalField) {
        // Check existing configurations
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM metadata_display_config WHERE show_in_modal = 1");
        $existingCount = $stmt->fetch()['count'];
        
        if ($existingCount == 0) {
            echo json_encode([
                'success' => false,
                'message' => 'At least one field must be visible in preview modals'
            ]);
            return;
        }
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO metadata_display_config 
            (field_id, show_on_card, show_in_modal, card_display_order, modal_display_order)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                show_on_card = VALUES(show_on_card),
                show_in_modal = VALUES(show_in_modal),
                card_display_order = VALUES(card_display_order),
                modal_display_order = VALUES(modal_display_order)
        ");
        
        foreach ($configurations as $config) {
            $stmt->execute([
                $config['field_id'],
                $config['show_on_card'] ?? 1,
                $config['show_in_modal'] ?? 1,
                $config['card_display_order'] ?? 0,
                $config['modal_display_order'] ?? 0
            ]);
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', 'Updated metadata display configuration');
        
        echo json_encode([
            'success' => true,
            'message' => 'Display configuration updated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function resetDisplayConfig($pdo, $currentUser) {
    $fieldId = $_POST['field_id'] ?? null;
    
    $pdo->beginTransaction();
    
    try {
        if ($fieldId) {
            // Reset single field
            $stmt = $pdo->prepare("DELETE FROM metadata_display_config WHERE field_id = ?");
            $stmt->execute([$fieldId]);
            $message = 'Field display configuration reset to defaults';
        } else {
            // Reset all fields
            $pdo->exec("TRUNCATE TABLE metadata_display_config");
            $message = 'All display configurations reset to defaults';
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', $message);
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
```

### UI Component Structure

**Configuration Page Layout:**

```
┌─────────────────────────────────────────────────────────────┐
│  Metadata Display Configuration                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Instructions: Configure which fields appear on file │   │
│  │ cards and preview modals, and in what order.        │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Field Name    │ Card │ Modal │ Card Order │ Modal Order│
│  ├──────────────────────────────────────────────────────┤  │
│  │ Author        │  ☑   │  ☑    │     1      │     1     │  │
│  │ Publisher     │  ☑   │  ☑    │     2      │     2     │  │
│  │ ISBN          │  ☐   │  ☑    │     3      │     3     │  │
│  │ Description   │  ☐   │  ☑    │     4      │     4     │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  [Reset All]  [Preview]                    [Save Changes]  │
└─────────────────────────────────────────────────────────────┘
```

**Preview Panel:**
Shows a mock file card and preview modal with current configuration applied in real-time.

### CSS Styling

**File:** `assets/css/pages/metadata-display.css`

Key styles:
- Configuration table with sortable rows
- Toggle switches for visibility
- Number inputs for display order
- Preview panel with split view (card vs modal)
- Drag handles for reordering
- Success/error message styling

### JavaScript Functionality

**File:** `assets/js/pages/metadata-display.js`

Key features:
- Table row drag-and-drop for reordering
- Auto-update display order on drag
- Real-time preview updates
- Form validation
- Bulk operations
- Confirmation dialogs
- AJAX save/reset operations

## Security Considerations

### Authentication and Authorization

- All configuration endpoints require admin authentication
- Role-based access control enforced at API level
- Session validation on every request
- CSRF token validation for state-changing operations

### Input Validation

- Server-side validation of all inputs
- Type checking (integers, booleans)
- Range validation (non-negative orders)
- SQL injection prevention via prepared statements
- XSS prevention via output escaping

### Data Integrity

- Foreign key constraints prevent orphaned configurations
- Transactions ensure atomic updates
- Unique constraints prevent duplicate configurations
- Cascade deletes maintain referential integrity

### Error Information Disclosure

- Generic error messages for users
- Detailed errors only in development mode
- No database structure exposed in errors
- Activity logging for audit trail

## Performance Optimization

### Database Optimization

**Indexes:**
- Composite index on (show_on_card, card_display_order) for card queries
- Composite index on (show_in_modal, modal_display_order) for modal queries
- Index on field_id for joins

**Query Optimization:**
- Single query loads all configuration (LEFT JOIN)
- COALESCE provides defaults without separate queries
- Prepared statements for repeated queries

### Caching Strategy

**Request-Level Caching:**
- Configuration loaded once per page request
- Stored in static variable within helper function
- Cleared automatically at end of request

**Future Enhancements:**
- Redis/Memcached for cross-request caching
- Cache invalidation on configuration update
- TTL-based cache expiration

### Frontend Optimization

**Lazy Loading:**
- Preview modal metadata loaded on demand
- Configuration UI loads only when accessed

**Debouncing:**
- Preview updates debounced during rapid changes
- Auto-save debounced during drag operations

## Deployment Plan

### Phase 1: Database Migration
1. Run migration script to create metadata_display_config table
2. Verify table structure and indexes
3. Create default configurations for existing fields
4. Test rollback procedure

### Phase 2: Backend Implementation
1. Deploy helper functions
2. Deploy API endpoints
3. Test API with Postman/curl
4. Verify error handling

### Phase 3: Configuration UI
1. Deploy configuration page
2. Deploy JavaScript and CSS
3. Test CRUD operations
4. Test validation and error handling

### Phase 4: Display Integration
1. Update dashboard.php to use display configuration
2. Update public.php to use display configuration
3. Update browse.php to use display configuration
4. Test field visibility and ordering

### Phase 5: Testing and Validation
1. Run unit tests
2. Run property-based tests
3. Perform manual testing
4. Performance testing
5. Security audit

### Phase 6: Documentation and Training
1. Update user documentation
2. Create admin guide
3. Record training video
4. Announce feature to users

## Rollback Plan

If issues are discovered after deployment:

1. **Immediate Rollback:**
   - Revert code changes to previous version
   - Display configuration will be ignored
   - All fields will display (safe fallback)

2. **Database Rollback:**
   - Drop metadata_display_config table if needed
   - No data loss in other tables
   - Custom metadata continues to work

3. **Partial Rollback:**
   - Keep database changes
   - Revert only problematic code
   - Fix and redeploy

## Future Enhancements

### Out of Scope for Initial Release

1. **Per-User Display Preferences:**
   - Allow individual users to customize their view
   - Requires user_id in configuration table

2. **Conditional Display Rules:**
   - Show/hide fields based on metadata values
   - Example: Show ISBN only for books

3. **Field Grouping:**
   - Group related fields together
   - Collapsible sections in preview modal

4. **Export/Import Configuration:**
   - Export configuration as JSON
   - Import configuration from file
   - Useful for multi-environment deployments

5. **Display Templates:**
   - Pre-defined configuration templates
   - Quick apply for common scenarios

6. **Field Width Control:**
   - Control how much space each field takes
   - Useful for responsive layouts

7. **Custom Field Formatting:**
   - Date format customization
   - Number format customization
   - URL auto-linking

