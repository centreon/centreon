#!/usr/bin/perl
use strict;
use warnings;

use Test2::V0;
use FindBin;
use lib "$FindBin::Bin/../../";
use centreon::common::logger;

# Each function test a different aspect of the library (roughtly one public function each).
# To be sure there is no side effect each test create it's own test object
sub test_severity {
    my $logger = centreon::common::logger->new();
    is($logger->severity(), "WARNING", "default severity should be warning.");

    for my $sev ('FATAL', 'fatal', 'ERROR', 'Error', 'WARNING', 'NOTICE', 'INFO', 'DEBUG') {

        is($logger->severity($sev), uc($sev), "severity $sev was correctly set.");
        is($logger->severity(), uc($sev), "getter was correct.");
    }
    $logger->severity("FATAL");
    is($logger->severity("toto"), -1, "if severity is unknown we don't change anything.");
}

# By default the logger should write to stdout, so we capture stdout to a variable and check what the object did write.
sub test_writeLogInfo_Stdout {
    my %options = @_;

    my $logger = centreon::common::logger->new();
    $logger->flush_output(enabled => 1);
    $logger->severity($options{severity});
    $logger->withpid($options{pid});
    my $out;
    my $logExemple = "this is an info log.";
    do {
        local *STDOUT;
        open STDOUT, ">", \$out;
        $logger->writeLogInfo($logExemple);
    };
    my $log = check_log_format(log => $out, severity => "INFO", pid => $options{pid});

    is($log, $logExemple, "log is the same as what we sent.");

    print "    written to original STDOUT : $out";
}

sub test_writeLogInfo_Stdout_is_empty {
    my %options = @_;

    my $logger = centreon::common::logger->new();
    $logger->flush_output(enabled => 1);
    $logger->severity($options{severity});
    $logger->withpid($options{pid});
    my $out;
    my $logExemple = "this is an info log.";
    do {
        local *STDOUT;
        open STDOUT, ">", \$out;
        $logger->writeLogInfo($logExemple);
    };
    is($out, undef, "log is not written if severity is not high enough : $options{severity}");
}

sub test_writeLogFunc {
    my %options = @_;
    my $logger = centreon::common::logger->new();
    $logger->flush_output(enabled => 1);
    $logger->severity("debug");
    $logger->withpid($options{pid});
    my $out;
    my $logExemple = "this is an info log.";
    do {
        local *STDOUT;
        open STDOUT, ">", \$out;
        # for now we just check the function exist, we should check the log is written correctly in the future.
        $logger->writeLogDebug($logExemple);
        $logger->writeLogInfo($logExemple);
        $logger->writeLogNotice($logExemple);
        $logger->writeLogWarning($logExemple);
        $logger->writeLogError($logExemple);
        eval {$logger->writeLogFatal($logExemple); }; # for now we just ignore the die()
    };
}

sub main {
    test_severity();
    test_writeLogInfo_Stdout(pid => 1, severity => "info");
    test_writeLogInfo_Stdout(pid => 0, severity => "info");
    test_writeLogInfo_Stdout(pid => 1, severity => "debug");
    test_writeLogInfo_Stdout(pid => 0, severity => "debug");
    # let's check the log is not written when the severity is not high enough
    test_writeLogInfo_Stdout_is_empty(pid => 1, severity => "error");
    test_writeLogInfo_Stdout_is_empty(pid => 0, severity => "notice");
    test_writeLogFunc(pid => 1);

    done_testing;
}
# Helper function
sub check_log_format {
    # the best way to check the date would be to mock the time() function, and to run get_date().
    # as it is a builtin function, it is possible but hard to setup and can have various hard to debug side effect.
    my %options = @_;

    my $regex = '^\[\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}\] \[' . $options{severity} . '\] ';
    # if there is pid we ignore it in the regex
    $options{pid} and $regex .= '\[\d+\] ';
    $regex .= '(.*)$';
    like($options{log}, qr/$regex/, "log format is respected.");
    $options{log} =~ /$regex/; # like() don't seem to return the matched string

    ok(defined($1), "the log is not empty");
    return $1;
}

&main;