# Design Document: Public Page Advanced Features

## Overview

This design document outlines the technical approach for implementing advanced UI/UX features on the public-facing archive page. The enhancements include:

1. **Admin Modal Consistency**: Adopting the public page modal design in the admin dashboard
2. **Sticky Navigation**: Dynamic navbar that becomes fixed on scroll with reorganized layout
3. **Infinite Scroll**: Automatic content loading with a 60-file limit and "Browse More" button
4. **Dynamic Filter Display**: Contextual filter summary in the results section

The implementation will use vanilla JavaScript for client-side interactions, PHP for server-side data fetching, and CSS for smooth transitions and animations. All features will be designed to work seamlessly with existing functionality without breaking current behavior.

## Architecture

### Component Structure

```
Public Page System
├── Frontend (Client-Side)
│   ├── Sticky Navigation Controller
│   │   ├── Scroll Event Handler
│   │   ├── Layout State Manager
│   │   └── Transition Animator
│   ├── Infinite Scroll Controller
│   │   ├── Scroll Position Monitor
│   │   ├── Content Loader
│   │   ├── Loading State Manager
│   │   └── Error Handler
│   └── Modal Controller (existing, to be reused)
│       ├── Modal Display Logic
│       └── Event Handlers
├── Backend (Server-Side)
│   ├── File Pagination API
│   │   ├── Query Builder
│   │   ├── Filter Processor
│   │   └── JSON Response Generator
│   └── Filter Display Generator
│       ├── Filter Value Sanitizer
│       └── Label Formatter
└── Styling (CSS)
    ├── Sticky Navigation Styles
    ├── Loading Indicators
    └── Transition Animations
```

### Data Flow

1. **Sticky Navigation Flow**:
   - User scrolls → Scroll event fires → Check scroll position → Update navbar state → Apply CSS classes → Trigger transitions

2. **Infinite Scroll Flow**:
   - User scrolls near bottom → Check if loading allowed → Fetch next page via AJAX → Receive JSON data → Render new cards → Attach event listeners → Update state

3. **Admin Modal Flow**:
   - Admin clicks file card → Extract data attributes → Populate modal with public design → Add admin action buttons → Display modal

4. **Filter Display Flow**:
   - Page loads → Read GET parameters → Sanitize values → Build filter label → Render above results

## Components and Interfaces

### 1. Sticky Navigation Controller

**Purpose**: Manages the navbar state transitions between normal and sticky modes based on scroll position.

**JavaScript Module**: `StickyNavController`

```javascript
class StickyNavController {
  constructor(navbar, searchBox, navLinks, threshold = 100) {
    this.navbar = navbar;
    this.searchBox = searchBox;
    this.navLinks = navLinks;
    this.threshold = threshold;
    this.isSticky = false;
    this.init();
  }

  init() {
    window.addEventListener('scroll', this.handleScroll.bind(this));
  }

  handleScroll() {
    const scrollY = window.scrollY;
    
    if (scrollY > this.threshold && !this.isSticky) {
      this.makeSticky();
    } else if (scrollY <= this.threshold && this.isSticky) {
      this.makeNormal();
    }
  }

  makeSticky() {
    this.isSticky = true;
    this.navbar.classList.add('sticky');
    this.moveSearchToNavbar();
    this.moveNavLinksRight();
  }

  makeNormal() {
    this.isSticky = false;
    this.navbar.classList.remove('sticky');
    this.moveSearchToHero();
    this.moveNavLinksLeft();
  }

  moveSearchToNavbar() {
    // Move search box from hero to navbar center
  }

  moveSearchToHero() {
    // Move search box back to hero section
  }

  moveNavLinksRight() {
    // Reposition nav links to right side
  }

  moveNavLinksLeft() {
    // Restore nav links to original position
  }
}
```

**CSS Classes**:
- `.public-header.sticky`: Applied when navbar is in sticky mode
- `.public-header.sticky .public-search-compact`: Compact search box in navbar
- `.public-header.sticky .public-nav`: Right-aligned navigation links

### 2. Infinite Scroll Controller

**Purpose**: Monitors scroll position and loads additional content automatically until the 60-file limit is reached.

**JavaScript Module**: `InfiniteScrollController`

```javascript
class InfiniteScrollController {
  constructor(container, options = {}) {
    this.container = container;
    this.currentPage = 1;
    this.totalLoaded = 12; // Initial page size
    this.maxFiles = options.maxFiles || 60;
    this.pageSize = options.pageSize || 12;
    this.threshold = options.threshold || 200;
    this.isLoading = false;
    this.hasMore = true;
    this.searchQuery = options.searchQuery || '';
    this.categoryFilter = options.categoryFilter || '';
    this.init();
  }

  init() {
    window.addEventListener('scroll', this.handleScroll.bind(this));
  }

  handleScroll() {
    if (this.isLoading || !this.hasMore) return;
    
    const scrollPosition = window.innerHeight + window.scrollY;
    const bottomPosition = document.documentElement.scrollHeight - this.threshold;
    
    if (scrollPosition >= bottomPosition) {
      this.loadMore();
    }
  }

  async loadMore() {
    if (this.totalLoaded >= this.maxFiles) {
      this.showBrowseMoreButton();
      return;
    }

    this.isLoading = true;
    this.showLoadingIndicator();

    try {
      const nextPage = this.currentPage + 1;
      const response = await this.fetchFiles(nextPage);
      
      if (response.success && response.documents.length > 0) {
        this.renderFiles(response.documents);
        this.currentPage = nextPage;
        this.totalLoaded += response.documents.length;
        
        if (this.totalLoaded >= this.maxFiles) {
          this.hasMore = false;
          this.showBrowseMoreButton();
        } else if (response.documents.length < this.pageSize) {
          this.hasMore = false;
          this.showEndMessage();
        }
      } else {
        this.hasMore = false;
        this.showEndMessage();
      }
    } catch (error) {
      this.showError(error);
    } finally {
      this.isLoading = false;
      this.hideLoadingIndicator();
    }
  }

  async fetchFiles(page) {
    const params = new URLSearchParams({
      page: page,
      limit: this.pageSize,
      q: this.searchQuery,
      category: this.categoryFilter
    });

    const response = await fetch(`${APP_URL}/backend/api/public-files.php?${params}`);
    return await response.json();
  }

  renderFiles(documents) {
    const grid = this.container.querySelector('.row');
    documents.forEach(doc => {
      const card = this.createFileCard(doc);
      grid.appendChild(card);
    });
    this.attachCardListeners();
  }

  createFileCard(doc) {
    // Create file card HTML element
  }

  attachCardListeners() {
    // Attach click listeners to new cards
  }

  showLoadingIndicator() {
    // Display loading spinner
  }

  hideLoadingIndicator() {
    // Hide loading spinner
  }

  showBrowseMoreButton() {
    // Display "Browse More" button
  }

  showEndMessage() {
    // Display "All files loaded" message
  }

  showError(error) {
    // Display error message with retry option
  }
}
```

### 3. Backend API Endpoint

**Purpose**: Provides paginated file data for infinite scroll requests.

**File**: `backend/api/public-files.php`

```php
<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json');

// Get parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(60, max(1, intval($_GET['limit']))) : 12;
$searchQuery = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category'] ?? '';

// Build WHERE clause
$whereClause = "WHERE n.deleted_at IS NULL";
$params = [];

if ($searchQuery) {
    $like = "%$searchQuery%";
    $whereClause .= "
        AND (
            n.title           LIKE ?
         OR n.keywords        LIKE ?
         OR n.description     LIKE ?
         OR n.publisher       LIKE ?
         OR n.edition         LIKE ?
         OR n.volume_issue    LIKE ?
         OR c.name            LIKE ?
         OR l.name            LIKE ?
         OR DATE_FORMAT(n.publication_date, '%Y')       LIKE ?
         OR DATE_FORMAT(n.publication_date, '%M %Y')    LIKE ?
         OR DATE_FORMAT(n.publication_date, '%M %d, %Y') LIKE ?
        )";
    $params = array_merge($params, array_fill(0, 11, $like));
}

if ($categoryFilter && $categoryFilter !== 'all') {
    $whereClause .= " AND n.category_id = ?";
    $params[] = $categoryFilter;
}

// Calculate offset
$offset = ($page - 1) * $limit;

// Fetch documents
$sql = "SELECT n.*, c.name as category_name, l.name as language_name
        FROM newspapers n 
        LEFT JOIN categories c ON n.category_id = c.id 
        LEFT JOIN languages l ON n.language_id = l.id
        $whereClause 
        ORDER BY n.created_at DESC 
        LIMIT ? OFFSET ?";

$queryParams = $params;
$queryParams[] = $limit;
$queryParams[] = $offset;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format documents for JSON response
    $formattedDocs = array_map(function($doc) {
        return [
            'id' => $doc['id'],
            'title' => $doc['title'],
            'thumbnail_path' => $doc['thumbnail_path'],
            'publication_date' => $doc['publication_date'],
            'publisher' => $doc['publisher'],
            'description' => $doc['description'],
            'category_name' => $doc['category_name'],
            'file_type' => $doc['file_type'],
            'is_bulk_image' => $doc['is_bulk_image'],
            'page_count' => $doc['page_count'],
            'volume_issue' => $doc['volume_issue'],
            'edition' => $doc['edition'],
            'language_name' => $doc['language_name'],
            'keywords' => $doc['keywords']
        ];
    }, $documents);

    echo json_encode([
        'success' => true,
        'documents' => $formattedDocs,
        'page' => $page,
        'count' => count($formattedDocs)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch documents'
    ]);
}
```

### 4. Admin Modal Adapter

**Purpose**: Adapts the admin dashboard modal to use the public page modal design while preserving admin functionality.

**Approach**: 
- Copy the public modal HTML structure to `views/dashboard.php`
- Modify the modal to include admin action buttons (Edit, Delete)
- Update `assets/js/pages/dashboard.js` to populate the new modal structure
- Maintain existing admin functionality (edit/delete operations)

**Modified Modal Structure**:
```html
<div id="adminFilePreviewModal" class="public-modal-backdrop">
    <div class="public-modal" role="dialog" aria-modal="true">
        <!-- Left: Image + Actions -->
        <div class="public-modal-left">
            <div class="public-modal-img-container">
                <img id="adminModalImg" src="" class="public-modal-img" alt="File Preview">
                <div id="adminModalNoImg" class="public-modal-no-img" style="display: none;">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>No preview available</span>
                </div>
            </div>
            <div class="public-modal-actions">
                <a id="adminModalReadBtn" href="#" target="_blank" class="public-read-btn">
                    <i class="bi bi-book-half"></i> Read Full Document
                </a>
                <!-- Admin-specific buttons -->
                <button id="adminModalEditBtn" class="admin-action-btn admin-edit-btn">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <button id="adminModalDeleteBtn" class="admin-action-btn admin-delete-btn">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>
        </div>

        <!-- Right: Metadata (same as public modal) -->
        <div class="public-modal-right">
            <!-- Same structure as public modal -->
        </div>
    </div>
</div>
```

### 5. Dynamic Filter Display

**Purpose**: Generates and displays a contextual summary of active filters above the search results.

**PHP Implementation** (in `public.php`):

```php
<?php
/**
 * Generate filter display label
 */
function generateFilterLabel($categoryFilter, $editionFilter, $dateFrom, $dateTo, $categories) {
    $parts = [];
    
    // Category
    if ($categoryFilter && $categoryFilter !== 'all') {
        $catName = 'Unknown Category';
        foreach ($categories as $cat) {
            if ($cat['id'] == $categoryFilter) {
                $catName = htmlspecialchars($cat['name']);
                break;
            }
        }
        $parts[] = $catName;
    } else {
        $parts[] = 'All Categories';
    }
    
    // Edition
    if ($editionFilter && $editionFilter !== 'all') {
        $parts[] = htmlspecialchars($editionFilter) . ' Edition';
    }
    
    // Date range
    if ($dateFrom || $dateTo) {
        if ($dateFrom && $dateTo) {
            $parts[] = htmlspecialchars($dateFrom) . ' - ' . htmlspecialchars($dateTo);
        } elseif ($dateFrom) {
            $parts[] = 'From ' . htmlspecialchars($dateFrom);
        } else {
            $parts[] = 'Until ' . htmlspecialchars($dateTo);
        }
    }
    
    return 'Showing: ' . implode(', ', $parts);
}

$filterLabel = generateFilterLabel($categoryFilter, $editionFilter ?? '', $dateFrom ?? '', $dateTo ?? '', $categories);
?>
```

**HTML Display**:
```html
<div class="public-filter-display">
    <span class="filter-label"><?= $filterLabel ?></span>
</div>
```

## Data Models

### File Document Model

```typescript
interface FileDocument {
  id: number;
  title: string;
  thumbnail_path: string | null;
  publication_date: string | null;
  publisher: string | null;
  description: string | null;
  category_name: string;
  file_type: string;
  is_bulk_image: boolean;
  page_count: number | null;
  volume_issue: string | null;
  edition: string | null;
  language_name: string | null;
  keywords: string | null;
}
```

### Infinite Scroll State

```typescript
interface ScrollState {
  currentPage: number;
  totalLoaded: number;
  maxFiles: number;
  pageSize: number;
  isLoading: boolean;
  hasMore: boolean;
  searchQuery: string;
  categoryFilter: string;
}
```

### Navbar State

```typescript
interface NavbarState {
  isSticky: boolean;
  scrollThreshold: number;
  searchBoxPosition: 'hero' | 'navbar';
  navLinksPosition: 'left' | 'right';
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Sticky Navigation State Consistency

*For any* scroll position, the navbar state (sticky or normal) should be consistent with the scroll threshold, meaning if scrollY > threshold then navbar is sticky, and if scrollY <= threshold then navbar is normal.

**Validates: Requirements 2.1, 2.5, 2.6**

### Property 2: Infinite Scroll Load Limit

*For any* sequence of scroll events, the total number of files loaded should never exceed the maximum limit of 60 files.

**Validates: Requirements 3.5, 3.6**

### Property 3: Infinite Scroll Idempotency

*For any* scroll position that triggers loading, if a load request is already in progress, no additional load request should be initiated (preventing duplicate requests).

**Validates: Requirements 3.12, 5.2**

### Property 4: Filter Display Completeness

*For any* combination of active filters (category, edition, date), the filter display label should include all and only the selected filter values, with no empty or undefined values displayed.

**Validates: Requirements 4.4, 4.6, 4.7**

### Property 5: Modal Event Handler Persistence

*For any* file card loaded via infinite scroll, clicking the card should open the preview modal with correct data, demonstrating that event handlers are properly attached to dynamically loaded content.

**Validates: Requirements 5.8**

### Property 6: Navbar Layout Reversibility

*For any* navbar state transition from normal to sticky and back to normal, the final layout should be identical to the initial layout (reversible transformation).

**Validates: Requirements 2.6, 2.9**

### Property 7: API Response Sanitization

*For any* file data returned by the pagination API, all string fields should be properly sanitized to prevent XSS vulnerabilities when rendered in the DOM.

**Validates: Requirements 4.8**

### Property 8: Loading State Mutual Exclusion

*For any* point in time, the infinite scroll controller should be in exactly one state: idle, loading, or error (mutual exclusion of states).

**Validates: Requirements 3.2, 3.9**

## Error Handling

### Sticky Navigation Errors

1. **Browser Compatibility**: Use feature detection for scroll events and CSS transforms
2. **Layout Shift**: Use CSS containment and fixed heights to prevent content jump
3. **Resize Handling**: Debounce resize events and recalculate positions

### Infinite Scroll Errors

1. **Network Failures**: 
   - Retry failed requests up to 2 times with exponential backoff
   - Display user-friendly error message with manual retry button
   - Log errors to console for debugging

2. **Empty Responses**:
   - Check response.documents.length before rendering
   - Display "All files loaded" message when no more content available
   - Disable further scroll triggers

3. **Malformed Data**:
   - Validate response structure before processing
   - Skip invalid documents and log warnings
   - Continue rendering valid documents

4. **Race Conditions**:
   - Use loading flag to prevent concurrent requests
   - Cancel pending requests on page navigation
   - Implement request debouncing

### Admin Modal Errors

1. **Missing Data**: Provide fallback values for missing metadata fields
2. **Image Load Failures**: Display placeholder when thumbnail fails to load
3. **Action Failures**: Show error toast when edit/delete operations fail

### Filter Display Errors

1. **Undefined Parameters**: Use null coalescing operator (??) for safe parameter access
2. **Invalid Category IDs**: Validate category ID against available categories
3. **XSS Prevention**: Use htmlspecialchars() on all user-provided filter values

## Testing Strategy

### Dual Testing Approach

This feature will use both unit tests and property-based tests for comprehensive coverage:

- **Unit tests**: Verify specific examples, edge cases, and error conditions
- **Property tests**: Verify universal properties across all inputs using a property-based testing library

### Unit Testing

**Test Framework**: Jest (JavaScript), PHPUnit (PHP)

**Unit Test Cases**:

1. **Sticky Navigation**:
   - Test navbar becomes sticky at exact threshold (100px)
   - Test navbar returns to normal at threshold
   - Test search box moves to correct position
   - Test nav links reposition correctly
   - Test transitions apply smoothly

2. **Infinite Scroll**:
   - Test initial page loads 12 files
   - Test subsequent pages load 12 files each
   - Test loading stops at 60 files
   - Test "Browse More" button appears after 60 files
   - Test loading indicator shows/hides correctly
   - Test error handling displays error message
   - Test retry functionality works

3. **Admin Modal**:
   - Test modal opens with correct data
   - Test admin buttons are present
   - Test edit button triggers edit flow
   - Test delete button triggers delete flow
   - Test modal matches public design

4. **Filter Display**:
   - Test "All Categories" displays when no category selected
   - Test category name displays when selected
   - Test edition appends to label
   - Test date range formats correctly
   - Test multiple filters concatenate properly
   - Test XSS prevention works

5. **API Endpoint**:
   - Test pagination returns correct page
   - Test limit parameter respected
   - Test search query filters results
   - Test category filter works
   - Test response format is valid JSON
   - Test error responses have correct status codes

### Property-Based Testing

**Test Framework**: fast-check (JavaScript), Hypothesis (PHP if needed)

**Configuration**: Minimum 100 iterations per property test

**Property Test Cases**:

Each property test will be tagged with: **Feature: public-page-advanced-features, Property {number}: {property_text}**

1. **Property 1: Sticky Navigation State Consistency**
   - Generate random scroll positions
   - Verify navbar state matches expected state based on threshold
   - Tag: **Feature: public-page-advanced-features, Property 1: Sticky Navigation State Consistency**

2. **Property 2: Infinite Scroll Load Limit**
   - Simulate multiple scroll events
   - Verify total loaded files never exceeds 60
   - Tag: **Feature: public-page-advanced-features, Property 2: Infinite Scroll Load Limit**

3. **Property 3: Infinite Scroll Idempotency**
   - Trigger rapid scroll events
   - Verify only one request is active at a time
   - Tag: **Feature: public-page-advanced-features, Property 3: Infinite Scroll Idempotency**

4. **Property 4: Filter Display Completeness**
   - Generate random filter combinations
   - Verify label includes all selected filters
   - Verify no empty values in label
   - Tag: **Feature: public-page-advanced-features, Property 4: Filter Display Completeness**

5. **Property 5: Modal Event Handler Persistence**
   - Load random number of pages via infinite scroll
   - Click random file cards
   - Verify modal opens correctly for all cards
   - Tag: **Feature: public-page-advanced-features, Property 5: Modal Event Handler Persistence**

6. **Property 6: Navbar Layout Reversibility**
   - Perform random scroll up/down sequences
   - Verify final layout matches initial layout when at top
   - Tag: **Feature: public-page-advanced-features, Property 6: Navbar Layout Reversibility**

7. **Property 7: API Response Sanitization**
   - Generate random file data with special characters
   - Verify all strings are properly escaped in response
   - Tag: **Feature: public-page-advanced-features, Property 7: API Response Sanitization**

8. **Property 8: Loading State Mutual Exclusion**
   - Simulate various scroll and network scenarios
   - Verify controller is always in exactly one state
   - Tag: **Feature: public-page-advanced-features, Property 8: Loading State Mutual Exclusion**

### Integration Testing

1. Test sticky navigation works with infinite scroll
2. Test filter display updates correctly with infinite scroll
3. Test admin modal works after infinite scroll loads new cards
4. Test all features work together without conflicts
5. Test browser back/forward navigation
6. Test page refresh resets state correctly

### Manual Testing

1. Test on different screen sizes (mobile, tablet, desktop)
2. Test on different browsers (Chrome, Firefox, Safari, Edge)
3. Test with slow network connections
4. Test with screen readers for accessibility
5. Test keyboard navigation
6. Test with JavaScript disabled (graceful degradation)
