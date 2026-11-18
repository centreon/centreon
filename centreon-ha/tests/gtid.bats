#!/usr/bin/env bats

load helpers

UUID_A=3e11fa47-71ca-11e1-9e33-c80aa9429562
UUID_B=8a94f357-aab4-11df-86ab-c80aa9429562

# mysql_gtid_set_count

@test "mysql_gtid_set_count: counts ranges and single transactions" {
  load_ocf_common 0
  run mysql_gtid_set_count "$UUID_A:1-5:7:10-12"
  [ "$output" = "9" ]
}

@test "mysql_gtid_set_count: adds up every UUID of the set" {
  load_ocf_common 0
  run mysql_gtid_set_count "$UUID_A:1-1000,$UUID_B:1-20"
  [ "$output" = "1020" ]
}

@test "mysql_gtid_set_count: ignores newlines, raw or escaped" {
  load_ocf_common 0
  run mysql_gtid_set_count "$UUID_A:1-1000,\\n$UUID_B:1-20"
  [ "$output" = "1020" ]
  run mysql_gtid_set_count "$UUID_A:1-1000,
$UUID_B:1-20"
  [ "$output" = "1020" ]
}

@test "mysql_gtid_set_count: skips GTID tags" {
  load_ocf_common 0
  run mysql_gtid_set_count "$UUID_A:1-5:mytag:1-3"
  [ "$output" = "8" ]
}

@test "mysql_gtid_set_count: counts nothing in an empty set or the initial 0" {
  load_ocf_common 0
  run mysql_gtid_set_count ""
  [ "$output" = "0" ]
  run mysql_gtid_set_count "0"
  [ "$output" = "0" ]
}

# greater_than_gtid

@test "greater_than_gtid: MySQL, the node with the transactions of the last source wins" {
  load_ocf_common 0
  # Both nodes replicated uuid A; after a failover, only one has all of uuid B
  greater_than_gtid "$UUID_A:1-100,$UUID_B:1-50" "$UUID_A:1-100,$UUID_B:1-49"
  ! greater_than_gtid "$UUID_A:1-100,$UUID_B:1-49" "$UUID_A:1-100,$UUID_B:1-50"
  ! greater_than_gtid "$UUID_A:1-100" "$UUID_A:1-100,$UUID_B:1-1"
}

@test "greater_than_gtid: MySQL, any GTID set beats the initial 0" {
  load_ocf_common 0
  greater_than_gtid "$UUID_A:1" 0
}

@test "greater_than_gtid: MariaDB compares the sequence numbers" {
  load_ocf_common 1
  greater_than_gtid 0-1-101 0-1-100
  ! greater_than_gtid 0-1-100 0-1-101
  greater_than_gtid 0-1-100 0
}
