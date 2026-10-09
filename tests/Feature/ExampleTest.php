<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La prueba de ejemplo que trae Laravel, retocada.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ ESTE ARCHIVO NO ES "PRUEBA DE EJEMPLO" NADA MÁS
 * ---------------------------------------------------------------------------
 *
 * Viene con el proyecto de fábrica y mira "/". Antes "/" era una vista de
 * bienvenida vacía y pública, así que la prueba pasaba. Ahora "/" es el panel de
 * inicio, que exige sesión porque enseña cuántas órdenes hay y cuánto dinero se ha
 * gastado, y una prueba de ejemplo que falla sola porque otra persona cambió la
 * portada no sirve para nada.
 *
 * Por eso ahora comprueba lo que tiene sentido: que la aplicación arranca y que
 * sin sesión la raíz manda al login. Lo que ya se comprueba en detalle —que el
 * panel se abre, que salen las cifras, que los tres roles lo ven— está en PanelTest,
 * que es donde toca.
 */
class ExampleTest extends TestCase
{
    /**
     * La raíz manda al login cuando no hay sesión.
     *
     * Se prueba sin autenticar a propósito. Con sesión el panel se abre y eso lo
     * comprueba PanelTest::test_el_panel_se_abre, que además mira el contenido.
     */
    public function test_sin_sesion_la_raiz_manda_al_login()
    {
        $this->get('/')->assertRedirect('/login');
    }
}
