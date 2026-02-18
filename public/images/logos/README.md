# Organization Logos

This directory contains logos for various organizations that use Eagles Ice Arena.

## Required Logo Files

Place the following image files in this directory:

### Organizations

1. **sayha_logo.png** - Spokane JR Chiefs / SAYHA (Spokane Amateur Youth Hockey Association)
2. **lcfsc_logo.png** - Lilac City Figure Skating Club
3. **old-timers_logo.png** - Old Timers Hockey Association
4. **jets_logo.png** - The Jets (Old Timers sub-company)
5. **grinders_logo.png** - The Grinders (Old Timers sub-company)
6. **braves_logo.png** - Spokane Braves
7. **gonzaga_logo.png** - Gonzaga University
8. **eagles_logo.png** - Eagles Ice Arena

## Image Specifications

- **Format**: PNG (preferred for transparency) or JPG
- **Size**: Recommended 200x200px to 400x400px
- **Aspect Ratio**: Square (1:1) or close to it works best
- **Background**: Transparent PNG recommended for best display

## How It Works

The system automatically detects which organization an event belongs to based on keywords in the customer field or event title:

- Events with "Jr Chiefs", "Jr. Chiefs", or "SAYHA" → `sayha_logo.png`
- Events with "LCFSC", "Lilac City", or "Figure Skating" → `lcfsc_logo.png`
- Events with "Old Timers" → `old-timers_logo.png`
- Events with "Jets" or "The Jets" → `jets_logo.png`
- Events with "Grinders" or "The Grinders" → `grinders_logo.png`
- Events with "Spokane Braves" or just "Braves" → `braves_logo.png`
- Events with "Gonzaga University" or "Gonzaga" → `gonzaga_logo.png`
- Events with "Eagles" or "Eagles Ice Arena" → `eagles_logo.png`

## Adding New Organizations

To add a new organization:

1. Add the logo image file to this directory
2. Update `app/Support/OrganizationLogoMapper.php` to include the new mapping
3. Add keywords that will match the organization name in event titles

## Notes

- If a logo file is missing, the event will display without a logo (graceful fallback)
- More specific keywords are matched first (e.g., "Spokane Braves" before "Braves")
- Logo matching is case-insensitive
