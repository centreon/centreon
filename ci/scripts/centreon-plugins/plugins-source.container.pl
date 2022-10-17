#!/usr/bin/env perl

use App::FatPacker;
use File::Copy::Recursive;
use File::Path;
use JSON;

my $plugins_dir = '/usr/local/src/centreon-plugins';
my $packaging_dir = '/usr/local/src/packaging-centreon-plugins';
my $build_dir = '/usr/local/src/build';

# Prepare destination directory.
File::Path::remove_tree($build_dir);
File::Path::make_path($build_dir);

# Set version within sources.
my $global_version = $ARGV[0];
do {
    local $^I = '.bak';
    local @ARGV = ($plugins_dir . '/centreon/plugins/script.pm');
    while (<>) {
        s/^my \$global_version = .*$/my \$global_version = '$global_version';/ig;
        print;
    }
};
do {
    local $^I = '.bak';
    local @ARGV = ($plugins_dir . '/centreon/plugins/script.pm');
    while (<>) {
        s/^my \$alternative_fatpacker = 0;$/my \$alternative_fatpacker = 1;/ig;
        print;
    }
};

# Browse all plugins.
chdir($packaging_dir);
@plugins = glob('centreon-plugin-*');
foreach $plugin (@plugins) {
    # Load plugin configuration file.
    print "Processing $plugin...\n";
    if (-f $plugin . '/pkg.json') {
        open($fh, '<', $plugin . '/pkg.json');
        my $json_content = do { local $/; <$fh> };
        close($fh);
        $config = JSON::decode_json($json_content);

        # Prepare plugin layout.
        chdir($plugins_dir);
        File::Path::remove_tree('lib');
        File::Path::make_path('lib');
        my @common_files = (
            'centreon/plugins/http.pm',
            'centreon/plugins/misc.pm',
            'centreon/plugins/mode.pm',
            'centreon/plugins/multi.pm',
            'centreon/plugins/options.pm',
            'centreon/plugins/output.pm',
            'centreon/plugins/perfdata.pm',
            'centreon/plugins/script.pm',
            'centreon/plugins/statefile.pm',
            'centreon/plugins/values.pm',
            'centreon/plugins/backend/http/curl.pm',
            'centreon/plugins/backend/http/curlconstants.pm',
            'centreon/plugins/backend/http/lwp.pm',
            'centreon/plugins/backend/http/useragent.pm',
            'centreon/plugins/alternative/Getopt.pm',
            'centreon/plugins/alternative/FatPackerOptions.pm',
            'centreon/plugins/passwordmgr/environment.pm',
            'centreon/plugins/passwordmgr/hashicorpvault.pm',
            'centreon/plugins/passwordmgr/keepass.pm',
            'centreon/plugins/passwordmgr/teampass.pm',
            'centreon/plugins/templates/catalog_functions.pm',
            'centreon/plugins/templates/counter.pm',
            'centreon/plugins/templates/hardware.pm'
        );
        foreach my $file ((@common_files, @{$config->{files}})) {
            print "  - $file\n";
            if (-f $file) {
                File::Copy::Recursive::fcopy($file, 'lib/' . $file);
            } elsif (-d $file) {
                File::Copy::Recursive::dircopy($file, 'lib/' . $file);
            }
        }
        # Remove __END__ for Centreon Connector Perl compatibility.
        system 'find', 'lib', '-name', '*.pm', '-exec', 'sed', '-i', ' /__END__/d', '{}', ';';

        # Fatpack plugin.
        my $fatpacker = App::FatPacker->new();
        my $content = $fatpacker->fatpack_file("centreon_plugins.pl");
        open($fh, '>', $build_dir . '/' . $config->{plugin_name});
        chmod 0755, $fh; # Add execution permission
        print $fh $content;
        close($fh);
    }

    # Get back in the packaging directory for the next plugin.
    chdir($packaging_dir);
}
