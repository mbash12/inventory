<?php


Route::get('/', function () {
    // return File::get(base_path().'/src/client/dist/index.html');
    return File::get(public_path('client/index.html'));
    // return "Sedang di update";
});
// Route::get('/a', function () {
//     return File::get(public_path('client/index.html'));
// });
// Route::get('/marketing', function () {
//     return view('welcome');
// })->where('any', '.*');