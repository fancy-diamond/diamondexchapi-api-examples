"""Print an exchange back/lay ladder for match odds, and the bookmaker rates
converted to decimal odds.

    python exchange_ladder.py [ev_...]   (default: the first event in play)
"""
import sys

from odds_api import get_json, inplay_event_ids, main, rate_to_decimal, tradeable

DEPTH = 3


def level(ladder, i):
    return f"{ladder[i]['price']:g} ({round(ladder[i]['size'])})" if ladder and len(ladder) > i else ""


def header(m):
    flag = f"{m['status']}, stale" if m.get("stale") else m["status"]
    print(f"\n{m['name'].upper()}  [{flag}]" + ("" if tradeable(m) else "  no bets now"))


def ladder(m):
    header(m)
    # Back prices read right to left (best next to the middle), lay left to right,
    # as on an exchange screen. Each level: decimal odds (amount available).
    print(f"{'':<22}{'Back':>{DEPTH * 14}}  |  Lay")
    for r in m["runners"]:
        back = "".join(f"{level(r.get('back'), i):>14}" for i in reversed(range(DEPTH)))
        lay = "".join(f"{level(r.get('lay'), i):<14}" for i in range(DEPTH))
        print(f"{r['name'][:21]:<22}{back}  |  {lay}".rstrip())
    r = next((x for x in m["runners"] if x.get("back")), None)
    if r:
        p = r["back"][0]["price"]
        print(f"A back stake of 100 on {r['name']} at {p:g} returns {100 * p:.2f} (profit {100 * (p - 1):.2f}).")


def bookmaker(m):
    header(m)
    # Bookmaker prices are rates: profit per 100 staked. decimal = 1 + rate / 100.
    print(f"{'Runner':<22}{'back rate':>10}{'decimal':>10}  |{'lay rate':>10}{'decimal':>10}")
    for r in m["runners"]:
        b = (r.get("back") or [None])[0]
        l = (r.get("lay") or [None])[0]
        dec = lambda x: f"{rate_to_decimal(x['price']):.2f}" if x else "-"
        print(f"{r['name'][:21]:<22}{(format(b['price'], 'g') if b else '-'):>10}{dec(b):>10}  |"
              f"{(format(l['price'], 'g') if l else '-'):>10}{dec(l):>10}")
    r = next((x for x in m["runners"] if x.get("back")), None)
    if r:
        rate = r["back"][0]["price"]
        print(f"Rate {rate:g} = decimal {rate_to_decimal(rate):.2f}: 100 on {r['name']} wins {rate:g}.")


def run():
    ids = sys.argv[1:2] or inplay_event_ids(1)
    if not ids:
        print("Nothing in play right now; pass an event id: python exchange_ladder.py ev_...")
        return
    ev = get_json("/v1/events/" + ids[0])
    print(f"{ev['name']}  ({ev['sport']}, {(ev.get('competition') or {}).get('name', '')})")
    mo = [m for m in ev["markets"] if m["type"] == "match_odds"]
    bm = [m for m in ev["markets"] if m["type"] == "bookmaker"]
    if not mo and not bm:
        print("This event has no match odds or bookmaker market right now.")
    for m in mo:
        ladder(m)
    for m in bm:
        bookmaker(m)


main(run)
