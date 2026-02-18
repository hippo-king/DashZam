# Logo Implementation Summary

## What Was Done

I've implemented a logo system for the events schedule that automatically displays organization logos based on the event **customer field** (with fallback to title data for accuracy).

## Files Created

1. **app/Support/OrganizationLogoMapper.php** - Helper class that maps organization names to logo files
2. **public/images/logos/** - Directory for storing logo image files
3. **public/images/logos/README.md** - Documentation on required logo files and specifications

## Files Modified

1. **app/Http/Controllers/PublicEventsController.php**
    - Added `use App\Support\OrganizationLogoMapper`
    - Added logo and organization data to each event in both `index()` and `timeline()` methods

2. **resources/views/public/events.blade.php**
    - Added logo display in mobile live event cards
    - Added logo display in desktop live event cards
    - Added logo display in upcoming events list

## Organization Mappings

The system recognizes the following organizations and their keywords:

| Organization                   | Keywords                                | Logo File              |
| ------------------------------ | --------------------------------------- | ---------------------- |
| Spokane JR Chiefs (SAYHA)      | "jr chiefs", "jr. chiefs", "sayha"      | sayha_logo.png         |
| Lilac City Figure Skating Club | "lcfsc", "lilac city", "figure skating" | lcfsc_logo.png         |
| Old Timers Hockey Association  | "old timers"                            | old-timers_logo.png    |
| The Jets                       | "the jets", "jets"                      | jets_logo.png          |
| The Grinders                   | "the grinders", "grinders"              | grinders_logo.png      |
| Spokane Braves                 | "spokane braves", "braves"              | braves_logo.png        |
| Gonzaga University             | "gonzaga university", "gonzaga"        | gonzaga_logo.png       |
| Eagles Ice Arena               | "eagles ice arena", "eagles"            | eagles_logo.png        |

## Next Steps - Add Your Logo Files

Place logo image files in `public/images/logos/` with these exact filenames:

- sayha_logo.png
- lcfsc_logo.png
- old-timers_logo.png
- jets_logo.png
- grinders_logo.png
- braves_logo.png
- gonzaga_logo.png
- eagles_logo.png

### Recommended Image Specs:

- Format: PNG (with transparent background) or JPG
- Size: 200x200px to 400x400px
- Aspect Ratio: Square (1:1) preferred
- Background: Transparent for best results

## How It Works

1. When events are loaded, the system examines each event's **customer field** first
2. If no match is found in the customer field, it searches the event title as a fallback
3. It searches for keywords that match known organizations
4. If a match is found, it adds the logo path to the event data
5. The view displays the logo next to the event title (if available)
6. If no logo file exists or no match is found, the event displays without a logo (graceful fallback)

**Priority:** Customer field → Event title → No logo

## Data Sources

The system extracts organization information from two sources (in priority order):

1. **Customer field** (`attributes.customer` or `attributes.customer_name`) - Primary source
2. **Event title** (`attributes.desc` or `attributes.title`) - Fallback if customer field doesn't match

This ensures accurate logo display based on the actual event owner rather than just title keywords.

## Testing

You can test with the mock events by setting `EVENTS_USE_MOCK=true` in your `.env` file. The mock data includes customer fields for "SAYHA", "Spokane Braves", "Old Timers", and "LCFSC" which will show logos once you add the image files.

## Adding More Organizations

To add a new organization:

1. Add the logo image file to `public/images/logos/`
2. Edit `app/Support/OrganizationLogoMapper.php`
3. Add entries to the `$logoMap` and `$priorityOrder` arrays
4. Add the keyword to organization name mapping in `getOrganizationName()` method
