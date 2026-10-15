=== Medical Before After Gallery ===
Contributors: agskanchana
Tags: before-after, medical, gallery, comparison, healthcare
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://buymeacoffee.com/wpplugindev

A simple before-after image gallery plugin designed for medical professionals and healthcare practices.

== Description ==

Showcase patient transformations with a clean before/after case gallery. Built for cosmetic surgery, dermatology, dental, and aesthetic clinics.

== Free vs Pro Features ==
Free Features:
- Basic Before-After Gallery
- Category Filtering
- Image Cropping
- Responsive Design
- Before-After View Switching
- Modal Case Navigation
- Category Navigation

Pro Features:
- All Free features
- Sub Category Support
- Multiple Before-After Pairs
- Advanced Filtering (Age, Gender, Procedure)
- Watermarking Capabilities
- Additional Images Carousel
- Sensitive Content Warning
- Detailed Before-After Case Information
- Premium Support

**Free Demo:** [View Free Demo](https://demo.medicalbeforeaftergallery.com/free/)

**Pro Demo:** [View Pro Demo](https://demo.medicalbeforeaftergallery.com/)

**Key Features:**

* **Interactive Image Comparison** – Smooth drag slider for before/after reveals
* **Category Management** – Organize cases by treatment types
* **Mobile Responsive** – Optimized for all devices
* **Professional Layouts** – Grid and masonry layouts
* **Custom Color Theming** – Match your brand colors
* **Multiple View Modes** – Split view, before-only, after-only options

**Perfect For:**
* Plastic Surgery Clinics
* Dermatology Practices
* Dental Offices
* Aesthetic Medicine Centers
* Weight Loss Clinics
* Hair Restoration Clinics

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/medical-before-after-gallery/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Medical Before After Gallery in your admin dashboard to configure settings
4. Create your first case study
5. Add the shortcode [medbeafgallery] to any page or post

== Frequently Asked Questions ==

= Can I customize the colors? =
Yes! You can set custom colors for the gallery interface.

= Does it work on mobile devices? =
Absolutely! The gallery is fully responsive and optimized for touch devices.

= Can I customize the gallery appearance? =
Yes, the plugin includes various layout options and customization settings.

== Screenshots ==

1. Gallery grid view
2. Interactive before/after slider in modal
3. Admin dashboard
4. Mobile responsive design
5. Category management interface

== Changelog ==

= 1.4.0 =
* Fixed: category tiles showing no icon (their SVG icons were being stripped)
* Fixed: share buttons opening two popup windows per click, and the second gallery on a page getting no working share buttons
* Fixed: carousel arrows covering the first and last category tiles
* Fixed: parent categories whose cases all sit in subcategories disappearing from the category bar, and listing no cases when clicked
* Fixed: a deleted image showing as a broken image instead of a placeholder
* Fixed: the settings screen showing defaults that were not actually in effect, so the first unchanged save quietly changed them; the "Settings saved" message now appears
* Fixed: case descriptions in embeds outside the main gallery (Pro's single-case embed) showing the wrong text
* Fixed: importing or creating cases from WP-CLI, the REST API or an import could stop part-way when the free case limit was reached
* Fixed: a PHP notice logged on every page without a gallery when WordPress debugging is on (the colour stylesheet was loaded everywhere)
* Fixed: on a site over the free plan's limits (for example after a Pro licence expires), parent categories missed some of their subcategories' cases, a category tile could open an empty gallery, and cards could read "Uncategorized"
* Improved: gallery cards load a properly sized thumbnail instead of the full-size original (about 78% less image data per card)
* Improved: placeholder cards while the gallery loads, instead of a blank space
* Improved: accessibility — the case window is a proper dialog (keyboard focus stays inside and returns afterwards), category tiles work with the keyboard, selected states are announced, text sizes follow the visitor's browser settings, and animations respect "reduce motion"
* Added: setting to show subcategory totals on category tiles (off by default)
* Added: "Delete data on uninstall" setting (off by default) — deleting the plugin now keeps your cases and categories unless you opt in; with it on, trashed cases and every site of a multisite network are cleaned up too
* Added: a notice when the Pro add-on is older than this version expects, and Pro embed shortcodes no longer print as raw text while Pro is inactive or unlicensed
* Compatibility: works with every Pro add-on release; older Pro versions keep working (share buttons fire once, arrows keep clear of the tiles)

= 1.3.9 =
* Fixed: HTML entities (such as &amp;) appearing as character codes in the case title and category inside the modal
* Fixed: the "Main View" label no longer shows when a case has only a single before/after image pair
* Improved: modal display on mobile devices

= 1.2.4 =
* Removed premium features for free version
* Enhanced drag performance for before/after slider
* Improved mobile responsiveness
* Added comprehensive color theming system
* Fixed modal navigation issues
* Optimized image loading performance

== Upgrade Notice ==

= 1.4.0 =
Fixes blank category tiles and double share popups, loads much lighter thumbnails, improves accessibility, and keeps your cases when the plugin is deleted unless you opt in. Works with every Pro add-on version.

= 1.3.9 =
Fixes garbled characters in the modal title and tidies the single-pair modal view.

= 1.2.4 =
Major update: simplified free version with core gallery features only.

== Support ==

For support and documentation, please visit: https://medicalbeforeaftergallery.com#support


== Privacy Policy ==

This plugin does not collect any personal data from your visitors. All image processing is done locally on your server.

== Third Party Libraries ==

This plugin includes Cocoen (https://github.com/koenoe/cocoen) – a touch-enabled before/after image comparison slider.
Cocoen is licensed under the MIT License (https://github.com/koenoe/cocoen/blob/main/LICENSE).
