<?php
use Illuminate\Support\Facades\Route;
use App\Accounting\Controller;
Route::prefix('muhasebe')->middleware(\App\Enerjisa\Http\Authenticate::class)->group(function() {
 Route::get('/urunler',[\App\Accounting\CatalogController::class,'index']);
 Route::get('/urunler/ekle',[\App\Accounting\CatalogController::class,'edit']);
 Route::get('/urunler/{id}/duzenle',[\App\Accounting\CatalogController::class,'edit'])->whereNumber('id');
 Route::post('/urunler',[\App\Accounting\CatalogController::class,'save']);
 Route::post('/urunler/{id}',[\App\Accounting\CatalogController::class,'save'])->whereNumber('id');
 Route::get('/directory',[\App\Accounting\DirectoryController::class,'search']);
 Route::post('/directory',[\App\Accounting\DirectoryController::class,'store'])->middleware('throttle:60,1');
 Route::get('/profil',[\App\Accounting\ProfileController::class,'edit']);
 Route::post('/profil',[\App\Accounting\ProfileController::class,'save']);
 Route::get('/teklifler/{id}',[Controller::class,'preview'])->whereNumber('id');
 Route::get('/teklifler',[Controller::class,'quotes']);
 Route::get('/rehber',[\App\Accounting\DirectoryController::class,'index']);
 Route::get('/rehber/{id}/duzenle',[\App\Accounting\DirectoryController::class,'edit'])->whereNumber('id');
 Route::post('/rehber/{id}',[\App\Accounting\DirectoryController::class,'update'])->whereNumber('id');
 Route::get('/rehber/ekle',[\App\Accounting\DirectoryController::class,'create']);
 Route::get('/',[Controller::class,'index']);
 Route::post('/entries/{id}/tags',[Controller::class,'tags'])->whereNumber('id');
 Route::delete('/entries/{id}',[Controller::class,'delete'])->whereNumber('id');
 Route::post('/entries/{id}/restore',[Controller::class,'restore'])->whereNumber('id');
 Route::post('/tahsilat',[\App\Accounting\ReceiptController::class,'handle']);
 Route::post('/entries',[Controller::class,'store']);
 Route::post('/entries/{id}/payments',[Controller::class,'pay'])->whereNumber('id');
 Route::post('/quotes',[Controller::class,'quote']);
 Route::post('/quotes/{id}',[Controller::class,'status'])->whereNumber('id');
});
