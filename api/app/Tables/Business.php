<?php

namespace App\Tables;

use App\Base\Table;

class Business extends Table
{
    protected string $table      = 'businesses';
    protected string $primaryKey = 'id';
    protected array  $fillable   = [
        'owner_id', 'name', 'slug', 'business_type',
        'gstin', 'pan', 'cin', 'is_gst_registered', 'gst_registered_date',
        'email', 'mobile', 'phone', 'website',
        'address_line1', 'address_line2', 'city', 'state_id', 'pincode',
        'bank_name', 'bank_account_no', 'bank_ifsc', 'bank_account_name', 'upi_id',
        'logo', 'currency', 'timezone', 'date_format', 'financial_year_start',
        'invoice_prefix', 'quote_prefix', 'invoice_terms', 'invoice_notes', 'signature',
        'active',
    ];
    protected array $guarded = ['id'];
}
