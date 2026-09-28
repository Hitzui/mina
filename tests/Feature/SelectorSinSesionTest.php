<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Un visitante sin sesion no llega al catalogo del modal de proveedores.
 *
 * Va en su propia clase, y no como un metodo mas de SelectorDeProveedoresTest,
 * por una razon tecnica que conviene tener escrita: no hay forma de quitar el
 * usuario que metio el actingAs una vez puesto. setUser(null) no se permite,
 * forgetUsers() no existe, y flushSession() solo vacia la sesion, no la memoria
 * del guardia, que es donde el actingAs deja al usuario. En la misma clase
 * que entra en el programa, este test no se puede escribir.
 *
 * No es una comprobacion de mas. Es el fallo que se produjo: la peticion del
 * buscador llego sin la cookie de sesion y el servidor contesto un 401 con un
 * {message: "Unauthenticated."} en la consola, sin más explicación. Con esto
 * se sabe que la ruta contesta eso a proposito y no por un descuido, y el modal
 * lo dice en pantalla en vez de dejar una lista de proveedores vacia.
 */
class SelectorSinSesionTest extends TestCase
{
    private function ajax(string $url)
    {
        return $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($url);
    }

    public function test_el_catalogo_responde_que_no_esta_autenticado(): void
    {
        $this->ajax('/inventario/proveedores/selector')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_una_visitante_ve_incluso_la_pantalla_va_al_login(): void
    {
        /*
         * El 401 es solo para las peticiones por ajax, que es como las hace
         * el buscador de la tabla. Una visita normal no ve un json: la mandan
         * a la pantalla de entrar, que es lo que debe pasar.
         */
        $this->get('/inventario/proveedores/selector')
            ->assertRedirect(route('login'));
    }
}
