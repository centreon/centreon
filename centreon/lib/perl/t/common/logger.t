#!/usr/bin/perl
use strict;
use warnings;

use Test2::V0;
use FindBin;
use lib "$FindBin::Bin/../../";
use centreon::common::logger;


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

sub test_logging_levels {
    my %options = @_;
    my $logger = centreon::common::logger->new();
    $logger->flush_output(enabled => 1);
    $logger->withpid($options{pid});
    $logger->withdate($options{with_date});

    my @levels = (
        { method => 'writeLogDebug',   severity => 'DEBUG',   message => 'Debug message' },
        { method => 'writeLogInfo',    severity => 'INFO',    message => 'Info message' },
        { method => 'writeLogNotice',  severity => 'NOTICE',  message => 'Notice message' },
        { method => 'writeLogWarning', severity => 'WARNING', message => 'Warning message' },
        { method => 'writeLogError',   severity => 'ERROR',   message => 'Error message' },
    );

    foreach my $level (@levels) {
        my $out;
        $logger->severity($level->{severity});
        do {
            local *STDOUT;
            open STDOUT, ">", \$out;
            my $met = $logger->can($level->{method});
            $met->($logger, $level->{message});
        };
        my $log = check_log_format(log => $out, severity => $level->{severity}, pid => $options{pid}, date => $options{with_date});
        is($log, $level->{message}, $level->{message} . " is correctly logged with pid set to " . $options{pid});
    }
}

sub test_writeLogFatal {
    my %options = @_;
    my $logger = centreon::common::logger->new();
    $logger->flush_output(enabled => 1);
    $logger->withpid($options{pid});
    $logger->withdate($options{with_date});
    $logger->severity('FATAL');

    my $message = 'Fatal error occurred';
    my $out;
    like(dies {
        do {
            local *STDOUT;
            open STDOUT, ">", \$out;
            $logger->writeLogFatal($message); };  },
        qr/Fatal error occurred/,
        "writeLogFatal dies as expected"
    );
}

sub main {

    test_severity();
    test_logging_levels(pid => 1, with_date => 1);
    test_logging_levels(pid => 0, with_date => 1);
    test_writeLogFatal(pid => 1, with_date => 1);
    test_writeLogFatal(pid => 0, with_date => 1);

    done_testing();
}

# Helper function
sub check_log_format {
    # the best way to check the date would be to mock the time() function, and to run get_date().
    # as it is a builtin function, it is possible but hard to setup and can have various hard to debug side effect.
    my %options = @_;

    my $regex = '^\[\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}\] \[' . $options{severity} . '\] ';
    # if there is pid we ignore it in the regex
    $options{pid} == 1 and $regex .= '\[\d+\] ';
    $regex .= '(.*)$';
    like($options{log}, qr/$regex/, "log format is respected.");
    $options{log} =~ /$regex/; # like() don't seem to return the matched string

    ok(defined($1), "the log is not empty");
    return $1;
}

main();