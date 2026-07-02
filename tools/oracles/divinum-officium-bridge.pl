#!/usr/bin/perl
# Optional maintainer bridge to the Divinum Officium Perl engine (#45 / #50).
#
# Divinum Officium (https://github.com/DivinumOfficium/divinum-officium) is a
# respected, independent implementation of the traditional office and calendar. This
# bridge is a thin wrapper that invokes DO's own headless entry point exactly as DO's
# regression harness does (regress/scripts/generate-diff.sh) and streams the resulting
# office to stdout, from which the validation adapter extracts the day's class.
#
# It is deliberately OPTIONAL: Perl and a DO checkout are not required to run the core
# suite. The adapter ({@see DivinumOfficiumOracle}) only invokes this bridge when a DO
# checkout is configured, and skips gracefully otherwise.
#
# Usage:
#   perl divinum-officium-bridge.pl <do-checkout> <YYYY-MM-DD> ["version"]
#   DIVINUM_OFFICIUM_PATH=<do-checkout> perl divinum-officium-bridge.pl <YYYY-MM-DD>
#
# "version" defaults to "Rubrics 1960 - 1960" (the edition our engine implements).

use strict;
use warnings;

my @args = @ARGV;
my $do_path;
$do_path = shift @args if @args && $args[0] !~ /^\d{4}-\d{2}-\d{2}$/;
$do_path //= $ENV{DIVINUM_OFFICIUM_PATH};

my $date    = shift @args;
my $version = shift @args // 'Rubrics 1960 - 1960';

die "usage: perl divinum-officium-bridge.pl <do-checkout> <YYYY-MM-DD> [version]\n"
  unless defined $do_path && defined $date;
die "not a Divinum Officium checkout: $do_path\n" unless -d $do_path;

my ($y, $m, $d) = $date =~ /^(\d{4})-(\d{2})-(\d{2})$/
  or die "date must be YYYY-MM-DD, got: $date\n";

# DO takes its date as MM-DD-YYYY.
my $do_date = "$m-$d-$y";

my $script = "$do_path/web/cgi-bin/horas/officium.pl";
die "officium.pl not found under $do_path (is this a DO checkout?)\n" unless -f $script;

# Invoke DO's own office script headlessly, as its regression harness does. Matins
# ('prayMatutinum') carries the resolved day's title and rank. DO writes the office
# (with a leading Set-Cookie header) to stdout; the adapter parses the class from it.
exec 'perl', $script,
  "version=$version",
  'command=prayMatutinum',
  "date=$do_date",
  'lang2=Latin'
  or die "failed to exec perl $script: $!\n";
