<?php

class Exporter {

    /**
     * Constructor
     */
    public function __construct() {
        $this->diffBasedDeploys = '';
        $this->crawled_links_file = '';

        // WP env settings
        $this->baseUrl = $_POST['baseUrl'];
        $this->wp_site_url = $_POST['wp_site_url'];
        $this->wp_site_path = $_POST['wp_site_path'];
        $this->wp_uploads_path = $_POST['wp_uploads_path'];

        $this->working_directory = isset( $_POST['workingDirectory'] )
            ? $_POST['workingDirectory']
            : $this->wp_uploads_path;

        $this->wp_uploads_url = $_POST['wp_uploads_url'];
    }


    /**
     * Capture last deployment
     *
     * @return void
     */
    public function capture_last_deployment() {
        require_once dirname( __FILE__ ) . '/../StaticHtmlOutput/Archive.php';

        $archive = new Archive();

        if ( ! $archive->currentArchiveExists() ) {
            return;
        }

        error_log( 'capturing last deployment: ' . $archive->path );

        // TODO: big cleanup required here, very iffy code
        // skip for first export state
        if ( is_file( $archive->path ) ) {
            $archiveDir = file_get_contents(
                $this->working_directory . '/WP-STATIC-CURRENT-ARCHIVE'
            );
            $previous_export = $archiveDir;
            $dir_to_diff_against = $this->wp_uploads_path . '/previous-export';

            if ( $this->diffBasedDeploys ) {
                $archiveDir = file_get_contents(
                    $this->working_directory . '/WP-STATIC-CURRENT-ARCHIVE'
                );

                $previous_export = $archiveDir;
                $dir_to_diff_against = $this->wp_uploads_path .
                    '/previous-export';

                if ( is_dir( $previous_export ) ) {
                    $cmd_shell = "rm -Rf $dir_to_diff_against && " .
                        "mkdir -p $dir_to_diff_against && " .
                        "cp -r $previous_export/* $dir_to_diff_against";
                    shell_exec( $cmd_shell );

                }
            } else {
                if ( is_dir( $dir_to_diff_against ) ) {
                    StaticHtmlOutput_FilesHelper::delete_dir_with_files(
                        $dir_to_diff_against
                    );
                    StaticHtmlOutput_FilesHelper::delete_dir_with_files(
                        $archiveDir
                    );
                }
            }//end if
        }//end if

    }


    /**
     * Pre-export cleanup
     *
     * @return void
     */
    public function pre_export_cleanup() {
        $files_to_clean = array(
            '/WP-STATIC-EXPORT-S3-FILES-TO-EXPORT',
            '/WP-STATIC-EXPORT-FTP-FILES-TO-EXPORT',
            '/WP-STATIC-EXPORT-GITHUB-FILES-TO-EXPORT',
            '/WP-STATIC-EXPORT-DROPBOX-FILES-TO-EXPORT',
            '/WP-STATIC-EXPORT-BUNNYCDN-FILES-TO-EXPORT',
            '/WP-STATIC-CRAWLED-LINKS',
            // '/WP-STATIC-INITIAL-CRAWL-LIST',
            // needed for zip download, diff deploys, etc
            // '/WP-STATIC-CURRENT-ARCHIVE',
            'WP-STATIC-EXPORT-LOG',
        );

        foreach ( $files_to_clean as $file_to_clean ) {
            $path_to_clean = $this->working_directory . '/' . $file_to_clean;
            if ( file_exists( $path_to_clean ) ) {
                unlink( $path_to_clean );
            }
        }
    }


    /**
     * Cleanup working files
     *
     * @return void
     */
    public function cleanup_working_files() {
        error_log( 'cleanup_working_files()' );
        // skip first explort state
        if (
            is_file(
                $this->working_directory . '/WP-STATIC-CURRENT-ARCHIVE'
            )
        ) {

            $handle = fopen(
                $this->working_directory . '/WP-STATIC-CURRENT-ARCHIVE',
                'r'
            );
            $this->archive_dir = stream_get_line( $handle, 0 );

            $dir_to_diff_against = $this->working_directory .
                '/previous-export';

            if ( is_dir( $dir_to_diff_against ) ) {
                // TODO: rewrite to php native in case of shared hosting
                // delete archivedir and then recursively copy
                $cmd_shell = "cp -r $dir_to_diff_against/* $this->archiveDir/";
                shell_exec( $cmd_shell );
            }
        }

        $files_to_clean = array(
            '/WP-STATIC-EXPORT-S3-FILES-TO-EXPORT',
            '/WP-STATIC-EXPORT-FTP-FILES-TO-EXPORT',
            '/WP-STATIC-EXPORT-GITHUB-FILES-TO-EXPORT',
            '/WP-STATIC-EXPORT-DROPBOX-FILES-TO-EXPORT',
            '/WP-STATIC-EXPORT-BUNNYCDN-FILES-TO-EXPORT',
            '/WP-STATIC-CRAWLED-LINKS',
            // '/WP-STATIC-INITIAL-CRAWL-LIST',
            // needed for zip download, diff deploys, etc
            // '/WP-STATIC-CURRENT-ARCHIVE',
            // 'WP-STATIC-EXPORT-LOG',
        );

        foreach ( $files_to_clean as $file_to_clean ) {
            $path_to_clean = $this->working_directory . '/' . $file_to_clean;
            if ( file_exists( $path_to_clean ) ) {
                unlink( $path_to_clean );
            }
        }
    }


    /**
     * Initialize cache files
     *
     * @return void
     */
    public function initialize_cache_files() {
        $this->crawled_links_file = $this->working_directory .
            '/WP-STATIC-CRAWLED-LINKS';

        $resource = fopen( $this->crawled_links_file, 'w' );
        fwrite( $resource, '' );
        fclose( $resource );
    }


    /**
     * Cleanup leftover archives
     *
     * @return void
     */
    public function cleanup_leftover_archives() {
        $leftover_files = preg_grep(
            '/^([^.])/',
            scandir( $this->working_directory )
        );

        foreach ( $leftover_files as $fileName ) {
            if ( strpos( $fileName, 'wp-static-html-output-' ) !== false ) {

                error_log(
                    'removing previous deployment: ' .
                    $this->working_directory . '/' . $fileName
                );

                if ( is_dir( $this->working_directory . '/' . $fileName ) ) {
                    StaticHtmlOutput_FilesHelper::delete_dir_with_files(
                        $this->working_directory . '/' . $fileName
                    );
                } else {
                    unlink( $this->working_directory . '/' . $fileName );
                }
            }
        }

        echo 'SUCCESS';
    }


    /**
     * Generate modified file list
     *
     * @return void
     */
    public function generateModifiedFileList() {
        // copy the preview crawl list within uploads dir to "modified list"
        copy(
            $this->wp_uploads_path . '/WP-STATIC-INITIAL-CRAWL-LIST',
            $this->wp_uploads_path . '/WP-STATIC-MODIFIED-CRAWL-LIST'
        );

        // process the modified list and make available for previewing from UI
        // $class = 'StaticHtmlOutput_FilesHelper';
        // $initial_file_list_count = $class::buildFinalFileList(
        // $viaCLI,
        // $this->additionalUrls,
        // $this->getWorkingDirectory(),
        // $this->uploadsURL,
        // $this->getWorkingDirectory(),
        // self::HOOK
        // );
        // copy the modified list to the working dir "finalized crawl list"
        copy(
            $this->wp_uploads_path . '/WP-STATIC-MODIFIED-CRAWL-LIST',
            $this->working_directory . '/WP-STATIC-FINAL-CRAWL-LIST'
        );

        /**
         * Use finalized crawl list from working dir to start the export
         * if list has been (re)generated in the frontend, use it, else
         * generate again at export time
         */
    }

}
