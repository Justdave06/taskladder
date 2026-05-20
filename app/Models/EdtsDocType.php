<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EdtsDocType extends Model
{
    protected $fillable = ['name', 'description'];

    public function documents()
    {
        return $this->belongsToMany(EdtsDocument::class, 'edts_document_doc_type', 'edts_doc_type_id', 'edts_document_id');
    }
}
