<?php
namespace App\Accounting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
class Ledger {
 public static function net(Collection $rows): int {return (int)($rows->whereIn('type',['receivable','payment'])->sum('amount')-$rows->whereIn('type',['receipt','debt'])->sum('amount'));}
 public static function balances(int $member,int $contact): Collection {return DB::table('accounting_entries')->where('member_id',$member)->where('directory_id',$contact)->whereNull('deleted_at')->get()->groupBy('currency')->map(fn($rows)=>self::net($rows));}
 public static function sql(): string {return "SUM(CASE WHEN type IN ('receivable','payment') THEN amount WHEN type IN ('receipt','debt') THEN -amount ELSE 0 END)";}
}
