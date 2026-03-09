# Bugfix Requirements Document

## Introduction

The thumbnails on the public.php landing page are displaying too large due to missing height constraints in the CSS. The `.public-file-thumbnail` and `.public-file-thumbnail-placeholder` classes currently have `aspect-ratio: 3/4` defined but no explicit height limitation, causing the thumbnails to scale beyond their intended size and negatively impacting the page layout and user experience.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN thumbnails are rendered on the public.php page THEN the system displays them at an oversized scale without height constraints

1.2 WHEN the aspect-ratio property is applied without a height limit THEN the system allows thumbnails to grow beyond reasonable dimensions

### Expected Behavior (Correct)

2.1 WHEN thumbnails are rendered on the public.php page THEN the system SHALL constrain them to a reasonable maximum height while maintaining the 3/4 aspect ratio

2.2 WHEN the aspect-ratio property is applied THEN the system SHALL enforce an explicit height constraint to prevent oversized display

### Unchanged Behavior (Regression Prevention)

3.1 WHEN thumbnails are displayed in other views (browse list view, compact view) THEN the system SHALL CONTINUE TO apply their existing height and aspect-ratio rules

3.2 WHEN thumbnails maintain the 3/4 aspect ratio THEN the system SHALL CONTINUE TO preserve this ratio in all contexts

3.3 WHEN thumbnail hover effects are triggered THEN the system SHALL CONTINUE TO apply the scale transformation as designed
