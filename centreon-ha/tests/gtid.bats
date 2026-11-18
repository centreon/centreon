#!/usr/bin/env bats

load helpers

UUID_A=3e11fa47-71ca-11e1-9e33-c80aa9429562
UUID_B=8a94f357-aab4-11df-86ab-c80aa9429562

# gtid_set_valid

@test "gtid_set_valid: MySQL accepts untagged and tagged GTID sets" {
  load_ocf_common 0
  run gtid_set_valid "$UUID_A:1-5"
  [ "$status" -eq 0 ]
  run gtid_set_valid "$UUID_A:batch:1"
  [ "$status" -eq 0 ]
  run gtid_set_valid "$UUID_A:1-5,$UUID_B:batch:1-3"
  [ "$status" -eq 0 ]
}

@test "gtid_set_valid: MySQL rejects empty sets and MariaDB GTIDs" {
  load_ocf_common 0
  run gtid_set_valid ""
  [ "$status" -eq 1 ]
  run gtid_set_valid "0-1-100"
  [ "$status" -eq 1 ]
}

@test "gtid_set_valid: MariaDB accepts domain-server-sequence GTIDs only" {
  load_ocf_common 1
  run gtid_set_valid "0-1-100"
  [ "$status" -eq 0 ]
  run gtid_set_valid ""
  [ "$status" -eq 1 ]
}

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

# A "! cmd" line does not fail a bats test (errexit ignores it): negative
# checks use run and $status.

@test "greater_than_gtid: MySQL, the node with the transactions of the last source wins" {
  load_ocf_common 0
  # Both nodes replicated uuid A; after a failover, only one has all of uuid B
  run greater_than_gtid "$UUID_A:1-100,$UUID_B:1-50" "$UUID_A:1-100,$UUID_B:1-49"
  [ "$status" -eq 0 ]
  run greater_than_gtid "$UUID_A:1-100,$UUID_B:1-49" "$UUID_A:1-100,$UUID_B:1-50"
  [ "$status" -eq 1 ]
  run greater_than_gtid "$UUID_A:1-100" "$UUID_A:1-100,$UUID_B:1-1"
  [ "$status" -eq 1 ]
}

@test "greater_than_gtid: MySQL, any GTID set beats the initial 0" {
  load_ocf_common 0
  run greater_than_gtid "$UUID_A:1" 0
  [ "$status" -eq 0 ]
}

@test "greater_than_gtid: equal sets do not win, so the first candidate stays" {
  load_ocf_common 0
  run greater_than_gtid "$UUID_A:1-100" "$UUID_A:1-100"
  [ "$status" -eq 1 ]
  load_ocf_common 1
  run greater_than_gtid 0-1-100 0-1-100
  [ "$status" -eq 1 ]
}

@test "greater_than_gtid: MariaDB compares the sequence numbers" {
  load_ocf_common 1
  run greater_than_gtid 0-1-101 0-1-100
  [ "$status" -eq 0 ]
  run greater_than_gtid 0-1-100 0-1-101
  [ "$status" -eq 1 ]
  run greater_than_gtid 0-1-100 0
  [ "$status" -eq 0 ]
}
