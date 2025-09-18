<?php
// src/recurrence.php
function rrule_parse(string $rrule): array {
  $out = ['FREQ'=>'', 'INTERVAL'=>1, 'BYDAY'=>[], 'BYMONTHDAY'=>null, 'BYMONTH'=>null, 'COUNT'=>null, 'UNTIL'=>null];
  foreach (explode(';', $rrule) as $part) {
    if (!$part) continue;
    [$k,$v] = array_map('trim', explode('=', $part, 2) + [null,null]);
    if (!$k) continue;
    switch ($k) {
      case 'FREQ': $out['FREQ'] = strtoupper($v ?? ''); break;
      case 'INTERVAL': $out['INTERVAL'] = max(1, (int)$v); break;
      case 'BYDAY': $out['BYDAY'] = $v ? array_filter(array_map('trim', explode(',', $v))) : []; break;
      case 'BYMONTHDAY': $out['BYMONTHDAY'] = ($v!==null && $v!=='') ? (int)$v : null; break;
      case 'BYMONTH': $out['BYMONTH'] = ($v!==null && $v!=='') ? (int)$v : null; break;
      case 'COUNT': $out['COUNT'] = ($v!==null && $v!=='') ? max(1, (int)$v) : null; break;
      case 'UNTIL': $out['UNTIL'] = $v ?: null; break; // YYYYMMDD or YYYYMMDDT000000Z – we’ll compare by Y-m-d
    }
  }
  return $out;
}

function rrule_until_to_date(?string $until): ?string {
  if (!$until) return null;
  // Accept YYYYMMDD or YYYYMMDDThhmmssZ -> take date part
  $d = preg_replace('/[^0-9]/', '', $until);
  if (strlen($d) < 8) return null;
  return substr($d,0,4).'-'.substr($d,4,2).'-'.substr($d,6,2);
}

/**
 * Expand RRULE occurrences within [$rangeStart, $rangeEnd] inclusive.
 * @param string $dtstart   (YYYY-mm-dd) first due date (from next_due)
 * @param string $rrule     RFC5545 subset
 * @param string $rangeStart range start (YYYY-mm-dd)
 * @param string $rangeEnd   range end (YYYY-mm-dd)
 * @return array of dates (YYYY-mm-dd) inside the range
 */
function rrule_expand(string $dtstart, string $rrule, string $rangeStart, string $rangeEnd): array {
  $p = rrule_parse($rrule);
  if (!$p['FREQ']) { // one-time
    return ($dtstart >= $rangeStart && $dtstart <= $rangeEnd) ? [$dtstart] : [];
  }

  $occ = [];
  $maxIt = 2000; // safety
  $countLeft = $p['COUNT'] ?? null;
  $untilDate = rrule_until_to_date($p['UNTIL']); // inclusive

  $start = new DateTimeImmutable($dtstart);
  $rangeS = new DateTimeImmutable($rangeStart);
  $rangeE = new DateTimeImmutable($rangeEnd);

  $freq = $p['FREQ'];
  $interval = max(1, (int)$p['INTERVAL']);

  // Helpers
  $ymd = fn(DateTimeImmutable $d) => $d->format('Y-m-d');
  $weekdayMap = ['MO'=>1,'TU'=>2,'WE'=>3,'TH'=>4,'FR'=>5,'SA'=>6,'SU'=>7];

  // Emit function (handles COUNT/UNTIL)
  $emit = function(DateTimeImmutable $d) use (&$occ,$rangeS,$rangeE,$untilDate,&$countLeft,$ymd) {
    if ($countLeft !== null && $countLeft <= 0) return false;
    if ($untilDate && $ymd($d) > $untilDate) return false;
    if ($d >= $rangeS && $d <= $rangeE) $occ[] = $ymd($d);
    if ($countLeft !== null) $countLeft--;
    return true;
  };

  // First occurrence is DTSTART itself
  $it = 0;

  if ($freq === 'DAILY') {
    $d = $start;
    while ($it++ < $maxIt) {
      if (!$emit($d)) break;
      // advance
      $d = $d->modify("+{$interval} day");
      // fast break if beyond both rangeE and until
      if ($d > $rangeE && (!$untilDate || $ymd($d) > $untilDate) && ($countLeft===null)) break;
    }
  }
  elseif ($freq === 'WEEKLY') {
    // If BYDAY empty, use start’s weekday; else use specified weekdays
    $bydays = $p['BYDAY'];
    if (!$bydays) {
      $wd = (int)$start->format('N'); // 1..7
      $bydays = array_keys(array_filter($weekdayMap, fn($n)=>$n===$wd));
    }
    // For each “interval week”, add selected weekdays
    // Start from the week of DTSTART; then step in INTERVAL weeks
    $anchorMonday = fn(DateTimeImmutable $d) => $d->modify('-'.((int)$d->format('N')-1).' days'); // back to Monday
    $wk = 0;
    $weekStart = $anchorMonday($start);
    while ($it++ < $maxIt) {
      // dates within this week per BYDAY
      foreach ($bydays as $dcode) {
        $n = $weekdayMap[$dcode] ?? null; if (!$n) continue;
        $candidate = $weekStart->modify('+'.($n-1).' days')->setTime(
          (int)$start->format('H'), (int)$start->format('i'), (int)$start->format('s')
        );
        // Only emit candidate >= DTSTART (avoid past weekdays in first week)
        if ($candidate < $start) continue;
        if (!$emit($candidate)) { break 2; }
      }
      $wk += $interval;
      $weekStart = $anchorMonday($start->modify("+{$wk} week"));
      // stop if well beyond ranges and no COUNT
      if ($weekStart > $rangeE && (!$untilDate || $ymd($weekStart) > $untilDate) && ($countLeft===null)) break;
    }
  }
  elseif ($freq === 'MONTHLY') {
    // Use BYMONTHDAY if provided; else use DTSTART day (clamped to month length)
    $baseDay = $p['BYMONTHDAY'] ?? (int)$start->format('j');
    $i = 0;
    $d = $start;
    while ($it++ < $maxIt) {
      // Target month/year
      $target = $start->modify("+{$i} month");
      // place on base day clamped
      $daysInMonth = (int)$target->format('t');
      $day = max(1, min($baseDay, $daysInMonth));
      $candidate = $target->setDate((int)$target->format('Y'), (int)$target->format('m'), $day)
                          ->setTime((int)$start->format('H'), (int)$start->format('i'), (int)$start->format('s'));
      if ($candidate >= $start) {
        if (!$emit($candidate)) break;
      }
      $i += $interval;
      // early stop
      if ($candidate > $rangeE && (!$untilDate || $ymd($candidate) > $untilDate) && ($countLeft===null)) break;
    }
  }
  elseif ($freq === 'YEARLY') {
    // Use BYMONTH & BYMONTHDAY if provided; else use DTSTART’s month/day
    $baseMonth = $p['BYMONTH'] ?? (int)$start->format('n');
    $baseDay   = $p['BYMONTHDAY'] ?? (int)$start->format('j');
    $i = 0;
    while ($it++ < $maxIt) {
      $year = (int)$start->format('Y') + ($i * $interval);
      // clamp day to month length
      $daysInMonth = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $baseMonth)))->format('t');
      $day = max(1, min($baseDay, $daysInMonth));
      $candidate = (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $baseMonth, $day)))
                    ->setTime((int)$start->format('H'), (int)$start->format('i'), (int)$start->format('s'));
      if ($candidate >= $start) {
        if (!$emit($candidate)) break;
      }
      $i += 1;
      if ($candidate > $rangeE && (!$untilDate || $ymd($candidate) > $untilDate) && ($countLeft===null)) break;
    }
  }
  else {
    // Unsupported FREQ -> treat as one-time
    if ($start >= $rangeS && $start <= $rangeE) $occ[] = $ymd($start);
  }

  sort($occ);
  return $occ;
}
