#!/usr/bin/env bash
# Print an exchange back/lay ladder for match odds, and the bookmaker rates
# converted to decimal odds (decimal = 1 + rate / 100).
#   ./exchange-ladder.sh [ev_...]   (default: the first event in play)
cd "$(dirname "$0")"; source ./common.sh

ID="${1:-$(inplay_ids 1)}"
[ -n "$ID" ] || { echo "Nothing in play right now; pass an event id: ./exchange-ladder.sh ev_..."; exit 0; }

api "/v1/events/$ID" | jq -r '
  def lpad($n): (" " * ([$n - length, 0] | max)) + .;
  def rpad($n): . + " " * ([$n - length, 0] | max);
  def lvl($l): if $l then "\($l.price) (\($l.size | round))" else "" end;
  def dec($l): if $l then (1 + $l.price / 100) * 100 | round / 100 | tostring else "-" end;
  def head: "\n\(.name | ascii_upcase)  [\(if .stale then "\(.status), stale" else .status end)]\(if .status == "OPEN" and (.stale | not) then "" else "  no bets now" end)";
  "\(.name)  (\(.sport), \(.competition.name // ""))",
  # Back reads right to left (best next to the middle), lay left to right. Each level: decimal odds (amount available).
  (.markets[] | select(.type == "match_odds") |
    head, "\("" | rpad(22))\("Back" | lpad(42))  |  Lay",
    (.runners[] | . as $r |
      "\(.name[:21] | rpad(22))\([2, 1, 0] | map(lvl($r.back[.]?) | lpad(14)) | join(""))  |  \([0, 1, 2] | map(lvl($r.lay[.]?) | rpad(14)) | join("") | sub(" +$"; ""))"),
    ([.runners[] | select(.back[0])][0] | if . then
      "A back stake of 100 on \(.name) at \(.back[0].price) returns \(100 * .back[0].price | round)." else empty end)),
  (.markets[] | select(.type == "bookmaker") |
    head, "\("Runner" | rpad(22))\("back rate" | lpad(10))\("decimal" | lpad(10))  |\("lay rate" | lpad(10))\("decimal" | lpad(10))",
    (.runners[] | "\(.name[:21] | rpad(22))\((.back[0].price // "-") | tostring | lpad(10))\(dec(.back[0]) | lpad(10))  |\((.lay[0].price // "-") | tostring | lpad(10))\(dec(.lay[0]) | lpad(10))"),
    ([.runners[] | select(.back[0])][0] | if . then
      "Rate \(.back[0].price) = decimal \(dec(.back[0])): 100 on \(.name) wins \(.back[0].price)." else empty end))'
