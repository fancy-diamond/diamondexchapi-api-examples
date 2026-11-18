// Print an exchange back/lay ladder for match odds, and the bookmaker rates
// converted to decimal odds.
//   node exchange-ladder.mjs [ev_…]     (default: the first event in play)
import { get, inplayEventIds, run } from './lib/api.mjs'
import { rateToDecimal, tradeable } from './lib/prices.mjs'

const DEPTH = 3
const fmt = (l) => (l ? `${l.price} (${Math.round(l.size)})` : '').padStart(14)

function ladder(m) {
  console.log(`\n${m.name.toUpperCase()}  [${m.stale ? m.status + ', stale' : m.status}]${tradeable(m) ? '' : '  no bets now'}`)
  // Back prices read right to left (best next to the middle), lay left to right,
  // as on an exchange screen. Each level: decimal odds (amount available).
  console.log(`${''.padEnd(22)}${'Back'.padStart(DEPTH * 14)}  |  Lay`)
  for (const r of m.runners) {
    const back = Array.from({ length: DEPTH }, (_, i) => fmt(r.back?.[i])).reverse().join('')
    const lay = Array.from({ length: DEPTH }, (_, i) => fmt(r.lay?.[i]).trimStart().padEnd(14)).join('')
    console.log(`${r.name.slice(0, 21).padEnd(22)}${back}  |  ${lay}`.trimEnd())
  }
  const r = m.runners.find((x) => x.back?.[0])
  if (r) console.log(`A back stake of 100 on ${r.name} at ${r.back[0].price} returns ${(100 * r.back[0].price).toFixed(2)} (profit ${(100 * (r.back[0].price - 1)).toFixed(2)}).`)
}

function bookmaker(m) {
  console.log(`\n${m.name.toUpperCase()}  [${m.stale ? m.status + ', stale' : m.status}]${tradeable(m) ? '' : '  no bets now'}`)
  // Bookmaker prices are rates: profit per 100 staked. decimal = 1 + rate / 100.
  console.log(`${'Runner'.padEnd(22)}${'back rate'.padStart(10)}${'decimal'.padStart(10)}  |${'lay rate'.padStart(10)}${'decimal'.padStart(10)}`)
  for (const r of m.runners) {
    const b = r.back?.[0], l = r.lay?.[0]
    const d = (x) => (x ? rateToDecimal(x.price).toFixed(2) : '-').padStart(10)
    console.log(`${r.name.slice(0, 21).padEnd(22)}${String(b?.price ?? '-').padStart(10)}${d(b)}  |${String(l?.price ?? '-').padStart(10)}${d(l)}`)
  }
  const r = m.runners.find((x) => x.back?.[0])
  if (r) console.log(`Rate ${r.back[0].price} = decimal ${rateToDecimal(r.back[0].price).toFixed(2)}: 100 on ${r.name} wins ${r.back[0].price}.`)
}

run(async () => {
  const id = process.argv[2] || (await inplayEventIds(1))[0]
  if (!id) return console.log('Nothing in play right now; pass an event id: node exchange-ladder.mjs ev_…')
  const { data: ev } = await get('/v1/events/' + id)
  console.log(`${ev.name}  (${ev.sport}, ${ev.competition?.name ?? ''})`)
  const mo = ev.markets.filter((m) => m.type === 'match_odds')
  const bm = ev.markets.filter((m) => m.type === 'bookmaker')
  if (!mo.length && !bm.length) console.log('This event has no match odds or bookmaker market right now.')
  mo.forEach(ladder)
  bm.forEach(bookmaker)
})
