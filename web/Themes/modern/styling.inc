<?php
declare(strict_types=1);
namespace MRBS;

// ============================================================
// MRBS Modern Theme — Indigo / Slate design system
// ============================================================

// Core palette
$body_background_color          = '#F8F9FC';
$standard_font_color            = '#111827';
$header_font_color              = '#ffffff';
$highlight_font_color           = '#4F46E5';
$color_key_font_color           = $standard_font_color;

// Topbar (was "banner")
$banner_back_color              = '#ffffff';
$banner_border_color            = '#E5E7EB';
$banner_font_color              = $standard_font_color;
$banner_nav_hover_color         = '#F3F4F6';

// Mini-calendar highlights
$minical_view_color             = '#4F46E5';
$minical_today_color            = '#EEF2FF';

// Table headers
$header_back_color              = '#F9FAFB';
$flatpickr_highlight_color      = '#4F46E5';

$admin_table_header_back_color  = '#4F46E5';
$admin_table_header_sep_color   = '#4338CA';
$admin_table_header_font_color  = '#ffffff';
$admin_table_border_color       = '#E5E7EB';

// Main calendar grid
$main_table_border_color                = '#E5E7EB';
$main_table_header_border_color         = '#E5E7EB';
$main_table_body_h_border_color         = '#F9FAFB';
$main_table_body_v_border_color         = '#E5E7EB';
$main_table_month_color                 = '#ffffff';
$main_table_month_weekend_color         = '#F9FAFB';
$main_table_month_holiday_color         = '#F3F4F6';
$main_table_month_weekend_holiday_color = '#ECEFF1';
$main_table_month_invalid_color         = '#F3F4F6';
$main_table_slot_invalid_color          = '#F3F4F6';
$main_table_slot_private_type_color     = '#F3F4F6';
$main_table_labels_back_color           = '#F9FAFB';
$timeline_color                         = '#4F46E5';

// Print overrides
$main_table_border_color_print        = '#E5E7EB';
$main_table_header_border_color_print = '#E5E7EB';
$main_table_body_h_border_color_print = '#E5E7EB';
$main_table_body_v_border_color_print = '#E5E7EB';
$header_font_color_print              = '#111827';
$anchor_link_color_header_print       = '#111827';

// Report / Search
$report_table_border_color  = '#E5E7EB';
$report_h2_border_color     = '#4F46E5';
$report_h3_border_color     = '#6366F1';
$search_table_border_color  = '#E5E7EB';
$site_faq_entry_border_color = '#E5E7EB';

// Links
$anchor_link_color            = '#4F46E5';
$anchor_visited_color         = '#6366F1';
$anchor_hover_color           = '#3730A3';
$anchor_link_color_banner     = $standard_font_color;
$anchor_visited_color_banner  = $standard_font_color;
$anchor_hover_color_banner    = '#4F46E5';
$anchor_link_color_header     = $standard_font_color;
$anchor_visited_color_header  = $standard_font_color;
$anchor_hover_color_header    = '#4F46E5';

// Calendar row alternating colors
$column_hidden_color            = '#F3F4F6';
$calendar_hidden_color          = '#E5E7EB';
$row_highlight_color            = '#EEF2FF';
$row_even_color                 = '#ffffff';
$row_odd_color                  = '#F9FAFB';
$row_even_color_weekend         = '#F9FAFB';
$row_odd_color_weekend          = '#F3F4F6';
$row_even_color_holiday         = '#F3F4F6';
$row_odd_color_holiday          = '#ECEFF1';
$row_even_color_weekend_holiday = '#ECEFF1';
$row_odd_color_weekend_holiday  = '#E5E7EB';

// Tables (report / search / users)
$zebra_even_color = '#ffffff';
$zebra_odd_color  = '#F5F3FF';

$help_highlight_color = '#EEF2FF';

// Buttons
$button_color_stops = array('#EEF2FF', '#C7D2FE');
$button_inset_color = '#4F46E5';

// Booking type fill colors — modern pastel palette
$color_types = array(
    'A' => '#EEF2FF',   // indigo
    'B' => '#F0FDFA',   // teal
    'C' => '#FFFBEB',   // amber
    'D' => '#FFF1F2',   // rose
    'E' => '#FAF5FF',   // purple
    'F' => '#F0FDF4',   // green
    'G' => '#FEF2F2',   // red
    'H' => '#EFF6FF',   // blue
    'I' => '#FDF4FF',   // fuchsia
    'J' => '#ECFDF5',   // emerald
);

// Pending / approval
$outstanding_color         = '#F59E0B';
$pending_header_back_color = '#F9FAFB';
$series_entry_back_color   = '#FFFBEB';
$pending_control_color     = '#FEF3C7';
$attention_color           = '#F59E0B';

// Dimensions
$banner_border_width          = '0';
$banner_border_cell_width     = '0';
$main_table_border_width      = '0';
$main_table_cell_border_width = '1';
$main_cell_height             = '2em';

// Typography
$standard_font_family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
