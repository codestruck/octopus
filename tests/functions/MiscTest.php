<?php

    /**
     * @group core
     */
    class MiscTests extends PHPUnit_Framework_TestCase
    {

        function testInDevEnvironment() {

            $this->assertTrue(is_dev_environment(false, false, false), 'true when not live or staging');
            $this->assertFalse(is_dev_environment(true, false, false), 'false when live');
            $this->assertFalse(is_dev_environment(false, true, false), 'false when staging');
            $this->assertFalse(is_dev_environment(true, true, false), 'false when live and staging');

        }

        function testInLiveEnvironment() {

            $this->assertTrue(is_live_environment(false, false, false));
            $this->assertFalse(is_live_environment(true, false, false));
            $this->assertFalse(is_live_environment(false, true, false));
            $this->assertFalse(is_live_environment(true, true, false));

        }

        function testInStagingEnvironment() {

            $this->assertFalse(is_staging_environment(false, false, false));
            $this->assertFalse(is_staging_environment(true, false, false));
            $this->assertFalse(is_staging_environment(false, true, false));
            $this->assertFalse(is_staging_environment(true, true, false));

        }

        function testDetectStagingEnvironmentFromHostname() {

            $this->assertTrue(
                is_staging_environment(
                    null,
                    null,
                    false,
                    'dev.whatever.com'
                )
            );

            $this->assertFalse(
                is_staging_environment(
                    null,
                    null,
                    false,
                    'whatever.com'
                )
            );
        }

        function testDetectStagingEnvironmentFromPath() {

            $this->assertTrue(
                is_staging_environment(
                    null,
                    null,
                    false,
                    null,
                    '/dev/whatever'
                )
            );

            $this->assertTrue(
                is_staging_environment(
                    null,
                    null,
                    false,
                    null,
                    '/dev'
                )
            );

            $this->assertFalse(
                is_staging_environment(
                    null,
                    null,
                    false,
                    null,
                    '/content'
                )
            );
        }

        function testDetectDevEnvironmentFromHostname() {

            $domains = array(
                '.com' => false,
                '.org' => false,
                '.biz' => false,
                '.net' => false,
                '.mobi' => false,
                '.hinz' => true,
                '.bain' => true,
                '.estes' => true,
                '.local' => true
            );


            foreach($domains as $ext => $dev) {

                $this->assertEquals(
                    $dev,
                    is_dev_environment(null, null, false, 'whatever' . $ext),
                    'Failed: ' . $ext
                );
            }
        }

    }

?>
