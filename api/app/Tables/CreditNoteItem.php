<?php

namespace App\Tables;

use App\Base\Table;

class CreditNoteItem extends Table
{
    protected string $table      = 'credit_note_items';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = false;
    protected array  $fillable   = [
        'credit_note_id', 'product_id', 'description', 'hsn_sac', 'unit',
        'quantity', 'unit_price', 'taxable_amt',
        'gst_rate', 'cgst_amt', 'sgst_amt', 'igst_amt', 'total',
    ];
    protected array $guarded = ['id'];
}
