<?php
/** Collector adapted from the supplied JPD B2B Export v2.2; same live pricing and fallback rules. */
defined('ABSPATH') || exit;
class JPD_OD_Collector {
    private static function text( $value ) {
        return is_scalar( $value ) ? trim( wp_strip_all_tags( (string) $value ) ) : '';
    }

    private static function attr( $product, $names ) {
        if ( ! $product instanceof WC_Product ) { return ''; }
        foreach ( $names as $name ) {
            $value = self::text( $product->get_attribute( $name ) );
            if ( '' !== $value ) { return $value; }
        }
        return '';
    }

    private static function item_attribute( $item, $names ) {
        foreach ( $names as $name ) {
            foreach ( array( $name, 'attribute_' . $name ) as $key ) {
                $value = self::text( $item->get_meta( $key, true ) );
                if ( '' === $value ) { continue; }
                if ( taxonomy_exists( $name ) ) {
                    $term = get_term_by( 'slug', $value, $name );
                    if ( $term && ! is_wp_error( $term ) ) { $value = $term->name; }
                }
                return $value;
            }
        }
        return '';
    }

    private static function category( $id ) {
        $result = array( 'root' => 'ΧΩΡΙΣ ΚΑΤΗΓΟΡΙΑ', 'sub' => 'ΓΕΝΙΚΑ' );
        $terms = $id ? get_the_terms( $id, 'product_cat' ) : false;
        if ( ! $terms || is_wp_error( $terms ) ) { return $result; }
        usort( $terms, function( $a, $b ) { return $a->term_id <=> $b->term_id; } );
        $best = array();
        foreach ( $terms as $term ) {
            $chain = array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );
            $chain[] = $term->term_id;
            if ( count( $chain ) > count( $best ) ) { $best = $chain; }
        }
        foreach ( array( 0 => 'root', 1 => 'sub' ) as $index => $key ) {
            if ( ! isset( $best[ $index ] ) ) { continue; }
            $term = get_term( $best[ $index ], 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) { $result[ $key ] = $term->name; }
        }
        return $result;
    }

    private static function sizes( $parent ) {
        if ( ! $parent instanceof WC_Product ) { return array(); }
        $attributes = $parent->get_attributes();
        foreach ( array( 'pa_size', 'size' ) as $key ) {
            if ( ! isset( $attributes[ $key ] ) || ! $attributes[ $key ] instanceof WC_Product_Attribute ) { continue; }
            $attribute = $attributes[ $key ];
            if ( $attribute->is_taxonomy() ) {
                $terms = wc_get_product_terms( $parent->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
                $options = is_wp_error( $terms ) ? array() : $terms;
            } else { $options = $attribute->get_options(); }
            if ( $options ) { return array_map( 'strval', $options ); }
        }
        return array();
    }

    public static function collect( $order ) {
        $data = array( 'rows' => array(), 'qty' => 0, 'total' => 0.0, 'warnings' => 0 );
        $parents = array();
        $decimals = wc_get_price_decimals();
        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
            // Optional integration: false only for an explicitly cancelled line.
            if ( ! apply_filters( 'jpd_b2b_export_include_item', true, $item, $order ) ) { continue; }
            $product = $item->get_product();
            $found = $product instanceof WC_Product;
            $parent_id = $found ? ( $product->get_parent_id() ?: $product->get_id() ) : $item->get_product_id();
            $notes = array();
            if ( ! isset( $parents[ $parent_id ] ) ) {
                $parent = $found && $parent_id === $product->get_id() ? $product : ( $parent_id ? wc_get_product( $parent_id ) : false );
                $parents[ $parent_id ] = array(
                    'name' => $parent instanceof WC_Product ? self::text( $parent->get_name() ) : '',
                    'sku' => $parent instanceof WC_Product ? self::text( $parent->get_sku() ) : '',
                    'image' => $parent instanceof WC_Product ? $parent->get_image_id() : 0,
                    'category' => self::category( $parent_id ), 'sizes' => self::sizes( $parent ),
                );
            }
            $p = $parents[ $parent_id ];
            $name = $found ? ( $p['name'] ?: self::text( $product->get_name() ) ) : self::text( $item->get_name() );
            if ( '' === $name ) { $name = 'Άγνωστο προϊόν'; }
            // Do not label an inherited parent SKU as a missing variation's own SKU.
            $sku = $found ? self::text( $product->get_sku( $product->get_parent_id() ? 'edit' : 'view' ) ) : '';
            $parent_sku = $found ? ( $p['sku'] ?: $sku ) : '';
            if ( ! $found ) { $notes[] = 'Το προϊόν δεν βρέθηκε — περιλαμβάνεται η γραμμή παραγγελίας'; }
            if ( '' === $sku ) { $notes[] = 'Άγνωστο SKU'; }
            $color_names = array( 'pa_color', 'color', 'Color', 'pa_colour', 'colour' );
            $size_names = array( 'pa_size', 'size', 'Size' );
            // Existing products always use current attributes; do not mask missing current data.
            $color = $found ? self::attr( $product, $color_names ) : self::item_attribute( $item, $color_names );
            $size = $found ? self::attr( $product, $size_names ) : self::item_attribute( $item, $size_names );
            if ( ! $found && ( '' !== $color || '' !== $size ) ) { $notes[] = 'Χρώμα/μέγεθος από την παραγγελία'; }
            $uncertain = ! $found || '' === $color || '' === $size;
            if ( '' === $color ) { $color = 'Άγνωστο'; $notes[] = 'Άγνωστο χρώμα'; }
            if ( '' === $size ) { $size = 'Άγνωστο'; $notes[] = 'Άγνωστο μέγεθος'; }
            $qty = (float) $item->get_quantity();
            // If wholesale price is a custom meta field, adapt this filter to that field.
            $raw_price = $found ? apply_filters( 'jpd_b2b_export_unit_price', $product->get_price(), $product, $item, $order ) : '';
            $valid_price = is_scalar( $raw_price ) && '' !== trim( (string) $raw_price ) && is_numeric( $raw_price ) && is_finite( (float) $raw_price ) && (float) $raw_price >= 0;
            if ( $valid_price ) {
                $unit = (float) $raw_price;
                $total = round( $unit * $qty, $decimals );
                $source = 'Τρέχουσα τιμή';
            } else {
                $total = round( (float) $item->get_total(), $decimals );
                $unit = 0 != $qty ? $total / $qty : 0;
                $source = 'Αποθηκευμένη αξία γραμμής';
                $notes[] = 'Χρησιμοποιείται η αποθηκευμένη αξία γραμμής';
            }
            $row = array(
                'item_id' => $item_id, 'product_id' => $item->get_product_id(), 'variation_id' => $item->get_variation_id(),
                'parent_id' => $parent_id, 'parent_sku' => $parent_sku ?: 'Άγνωστο', 'sku' => $sku ?: 'Άγνωστο',
                'name' => $name, 'color' => $color, 'size' => $size, 'qty' => $qty, 'unit' => $unit, 'total' => $total,
                'root' => $p['category']['root'], 'sub' => $p['category']['sub'], 'size_order' => $p['sizes'],
                'image' => $found ? ( $product->get_image_id() ?: $p['image'] ) : 0,
                'group' => $uncertain ? 'line_' . $item_id : $parent_id . '_' . md5( $color ),
                'source' => $source, 'notes' => $notes,
            );
            $data['rows'][] = $row;
            $data['qty'] += $qty;
            $data['total'] += $total;
            if ( $notes ) { ++$data['warnings']; }
        }
        $data['total'] = round( $data['total'], $decimals );
        usort( $data['rows'], function( $a, $b ) {
            foreach ( array( 'root', 'sub', 'parent_sku', 'color' ) as $key ) {
                $result = strnatcasecmp( $a[ $key ], $b[ $key ] );
                if ( $result ) { return $result; }
            }
            $ai = array_search( $a['size'], $a['size_order'], true );
            $bi = array_search( $b['size'], $b['size_order'], true );
            $result = ( false === $ai ? PHP_INT_MAX : $ai ) <=> ( false === $bi ? PHP_INT_MAX : $bi );
            return $result ?: ( strnatcasecmp( $a['size'], $b['size'] ) ?: ( $a['item_id'] <=> $b['item_id'] ) );
        } );
        return $data;
    }

    public static function csv_text( $value ) {
        $value = self::text( $value );
        // Neutralize spreadsheet formulas in text columns; numeric columns stay numeric.
        return preg_match( '/^[=+@\-\t\r\n]/u', $value ) ? "'" . $value : $value;
    }

}
