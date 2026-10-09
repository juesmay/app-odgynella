<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\EvolutionController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\OdontogramController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RadiologyOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TodayController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'account'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/mi-cuenta', [AccountSettingsController::class, 'show'])->name('account');
    Route::put('/mi-cuenta/clave', [AccountSettingsController::class, 'password'])->middleware('throttle:10,1')->name('account.password');
    Route::post('/sede', [SedeController::class, 'switch'])->name('sede.switch');

    Route::get('/', [TodayController::class, 'index'])->name('today');
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda');

    Route::get('/citas/nueva', [AppointmentController::class, 'create'])->name('appointments.create');
    Route::get('/citas/horas', [AppointmentController::class, 'slots'])->name('appointments.slots');
    Route::post('/citas', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::patch('/citas/{appointment}/estado', [AppointmentController::class, 'status'])->name('appointments.status');

    Route::get('/pacientes', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/pacientes/nuevo', [PatientController::class, 'create'])->name('patients.create');
    Route::post('/pacientes', [PatientController::class, 'store'])->name('patients.store');
    Route::get('/pacientes/{patient}', [PatientController::class, 'show'])->name('patients.show');
    Route::put('/pacientes/{patient}', [PatientController::class, 'update'])->name('patients.update');
    Route::put('/pacientes/{patient}/antecedentes', [HistoryController::class, 'update'])->name('patients.history');
    Route::get('/pacientes/{patient}/completar', [PatientController::class, 'complete'])->name('patients.complete');
    Route::put('/pacientes/{patient}/completar', [PatientController::class, 'completeStore'])->name('patients.complete.store');
    Route::post('/pacientes/{patient}/no-continuo', [PatientController::class, 'lost'])->name('patients.lost');
    Route::post('/pacientes/{patient}/reactivar', [PatientController::class, 'reactivate'])->name('patients.reactivate');

    // Historia clínica (fase 2). Ver: todo el equipo. Escribir lo clínico: solo la doctora.
    Route::get('/pacientes/{patient}/historia', [HistoryController::class, 'show'])->name('patients.clinical');
    Route::get('/pacientes/{patient}/odontograma', [OdontogramController::class, 'show'])->name('patients.odontogram');
    Route::get('/pacientes/{patient}/evoluciones', [EvolutionController::class, 'index'])->name('patients.evolutions');
    Route::get('/pacientes/{patient}/fotos', [PhotoController::class, 'index'])->name('patients.photos');
    Route::post('/pacientes/{patient}/fotos', [PhotoController::class, 'store'])->name('patients.photos.store');
    Route::get('/pacientes/{patient}/fotos/{photo}', [PhotoController::class, 'file'])->name('patients.photos.file');
    Route::get('/pacientes/{patient}/consentimientos', [ConsentController::class, 'index'])->name('patients.consents');
    Route::get('/pacientes/{patient}/consentimientos/firmar', [ConsentController::class, 'create'])->name('consents.create');
    Route::post('/pacientes/{patient}/consentimientos', [ConsentController::class, 'store'])->name('consents.store');
    Route::get('/pacientes/{patient}/consentimientos/{consent}', [ConsentController::class, 'show'])->name('consents.show');
    Route::get('/pacientes/{patient}/consentimientos/{consent}/pdf', [ConsentController::class, 'pdf'])->name('consents.pdf');
    Route::get('/pacientes/{patient}/ordenes', [RadiologyOrderController::class, 'index'])->name('patients.orders');
    Route::get('/pacientes/{patient}/ordenes/{order}/pdf', [RadiologyOrderController::class, 'pdf'])->name('orders.pdf');

    Route::middleware('role:doctora')->group(function () {
        Route::put('/pacientes/{patient}/examen', [HistoryController::class, 'exam'])->name('patients.exam');
        Route::post('/pacientes/{patient}/diagnosticos', [HistoryController::class, 'addDiagnosis'])->name('patients.diagnoses.store');
        Route::delete('/pacientes/{patient}/diagnosticos/{diagnosis}', [HistoryController::class, 'removeDiagnosis'])->name('patients.diagnoses.destroy');
        Route::put('/pacientes/{patient}/odontograma/denticion', [OdontogramController::class, 'dentition'])->name('patients.odontogram.dentition');
        Route::put('/pacientes/{patient}/odontograma/{tooth}', [OdontogramController::class, 'update'])->whereNumber('tooth')->name('patients.odontogram.update');
        Route::post('/pacientes/{patient}/evoluciones', [EvolutionController::class, 'store'])->name('patients.evolutions.store');
        Route::get('/pacientes/{patient}/ordenes/nueva', [RadiologyOrderController::class, 'create'])->name('orders.create');
        Route::post('/pacientes/{patient}/ordenes', [RadiologyOrderController::class, 'store'])->name('orders.store');
    });

    Route::get('/cotizaciones', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/cotizaciones/nueva', [QuoteController::class, 'create'])->name('quotes.create');
    Route::post('/cotizaciones', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('/cotizaciones/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/cotizaciones/{quote}/editar', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::put('/cotizaciones/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::get('/cotizaciones/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
    Route::post('/cotizaciones/{quote}/aceptada', [QuoteController::class, 'accept'])->name('quotes.accept');

    Route::get('/caja', [CashController::class, 'index'])->name('cash.index');
    Route::post('/caja/abrir', [CashController::class, 'open'])->name('cash.open');
    Route::post('/caja/cerrar', [CashController::class, 'close'])->name('cash.close');

    Route::get('/pagos/nuevo', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/pagos', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/pagos/{payment}/recibo', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::post('/pagos/{payment}/anular', [PaymentController::class, 'void'])->middleware('role:doctora')->name('payments.void');

    // Fase 3: los números.
    Route::get('/pacientes/{patient}/cuenta', [AccountController::class, 'show'])->name('patients.account');
    Route::get('/cartera', [AccountController::class, 'receivables'])->name('receivables.index');
    Route::get('/gastos', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/gastos', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/gastos/{expense}/soporte', [ExpenseController::class, 'support'])->name('expenses.support');

    Route::middleware('role:doctora')->group(function () {
        Route::post('/gastos/{expense}/anular', [ExpenseController::class, 'void'])->name('expenses.void');
        Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reportes/exportar', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/configuracion', [SettingsController::class, 'index'])->name('settings');
        Route::post('/configuracion/servicios', [SettingsController::class, 'storeService'])->name('settings.services.store');
        Route::put('/configuracion/servicios/{service}', [SettingsController::class, 'updateService'])->name('settings.services.update');
        Route::put('/configuracion/horario', [SettingsController::class, 'schedule'])->name('settings.schedule');
        Route::put('/configuracion/cotizaciones', [SettingsController::class, 'quotes'])->name('settings.quotes');
        Route::post('/configuracion/marca', [SettingsController::class, 'brand'])->name('settings.brand');
        Route::post('/equipo', [AccountSettingsController::class, 'storeUser'])->name('team.store');
        Route::post('/equipo/{user}/clave', [AccountSettingsController::class, 'resetPassword'])->name('team.reset');
        Route::post('/equipo/{user}/activo', [AccountSettingsController::class, 'toggle'])->name('team.toggle');
        Route::get('/configuracion/logo', [SettingsController::class, 'logo'])->name('settings.logo');
        Route::post('/configuracion/consentimientos', [SettingsController::class, 'storeTemplate'])->name('settings.templates.store');
        Route::put('/configuracion/consentimientos/{template}', [SettingsController::class, 'updateTemplate'])->name('settings.templates.update');
    });
});
