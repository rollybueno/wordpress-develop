<?php
/**
 * Performance tests for get_blogs_of_user()
 *
 * @group user
 * @group performance
 */
class Tests_User_GetBlogsOfUser_Performance extends WP_UnitTestCase {

    /**
     * Test performance of get_blogs_of_user() with multiple users
     */
    public function test_get_blogs_of_user_performance() {
        if ( ! is_multisite() ) {
            $this->markTestSkipped( 'This test requires multisite.' );
        }

        // Create test users
        $user_ids = array();
        for ( $i = 1; $i <= 5; $i++ ) {
            $user_id = $this->factory->user->create( array(
                'user_login' => "testuser{$i}",
                'user_email' => "test{$i}@example.com"
            ) );
            $user_ids[] = $user_id;

            // Add user to multiple sites
            for ( $j = 1; $j <= $i * 2; $j++ ) {
                if ( $j <= 10 ) {
                    add_user_to_blog( $j, $user_id, 'subscriber' );
                }
            }
        }

        // Clear cache before testing
        wp_cache_flush();

        // Measure performance
        $start_time = microtime( true );
        $start_memory = memory_get_usage();

        $iterations = 50;
        for ( $i = 0; $i < $iterations; $i++ ) {
            foreach ( $user_ids as $user_id ) {
                $blogs = get_blogs_of_user( $user_id, false );
            }
        }

        $end_time = microtime( true );
        $end_memory = memory_get_usage();

        $total_time = ( $end_time - $start_time ) * 1000; // Convert to milliseconds
        $memory_used = $end_memory - $start_memory;

        // Assert performance thresholds
        $this->assertLessThan( 1000, $total_time, 'Total execution time should be less than 1000ms' );
        $this->assertLessThan( 1024 * 1024, $memory_used, 'Memory usage should be less than 1MB' );

        // Log performance metrics
        error_log( sprintf(
            'get_blogs_of_user() performance: %d iterations, %.2f ms total, %.2f ms avg per iteration, %.2f KB memory',
            $iterations * count( $user_ids ),
            $total_time,
            $total_time / ( $iterations * count( $user_ids ) ),
            $memory_used / 1024
        ) );
    }

    /**
     * Test database query count for get_blogs_of_user()
     */
    public function test_get_blogs_of_user_query_count() {
        if ( ! is_multisite() ) {
            $this->markTestSkipped( 'This test requires multisite.' );
        }

        global $wpdb;

        // Create a test user
        $user_id = $this->factory->user->create();
        add_user_to_blog( 1, $user_id, 'subscriber' );
        add_user_to_blog( 2, $user_id, 'subscriber' );

        // Clear cache and enable query logging
        wp_cache_flush();
        $wpdb->queries = array();

        // Call the function
        $blogs = get_blogs_of_user( $user_id, false );

        // Assert query count
        $this->assertLessThan( 5, count( $wpdb->queries ), 'Should use fewer than 5 queries' );

        // Log query details
        error_log( sprintf(
            'get_blogs_of_user() queries: %d total, %.4f ms total time',
            count( $wpdb->queries ),
            array_sum( array_column( $wpdb->queries, 1 ) ) * 1000
        ) );
    }

    /**
     * Test cache effectiveness for get_blogs_of_user()
     */
    public function test_get_blogs_of_user_cache_effectiveness() {
        if ( ! is_multisite() ) {
            $this->markTestSkipped( 'This test requires multisite.' );
        }

        // Create a test user
        $user_id = $this->factory->user->create();
        add_user_to_blog( 1, $user_id, 'subscriber' );

        // Clear cache
        wp_cache_flush();

        // First call (no cache)
        $start_time = microtime( true );
        $blogs1 = get_blogs_of_user( $user_id, false );
        $first_call_time = ( microtime( true ) - $start_time ) * 1000;

        // Second call (should use cache if implemented)
        $start_time = microtime( true );
        $blogs2 = get_blogs_of_user( $user_id, false );
        $second_call_time = ( microtime( true ) - $start_time ) * 1000;

        // Assert cache effectiveness
        $cache_effectiveness = ( ( $first_call_time - $second_call_time ) / $first_call_time ) * 100;
        
        // Log cache effectiveness
        error_log( sprintf(
            'get_blogs_of_user() cache effectiveness: %.1f%% (first: %.2f ms, second: %.2f ms)',
            $cache_effectiveness,
            $first_call_time,
            $second_call_time
        ) );

        // Note: This test documents current behavior, not necessarily asserting improvement
        // The actual improvement would be measured after implementing caching
    }
} 