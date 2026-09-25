<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Iniciar sesión' }}
    </x-slot>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        @vite(['resources/scss/light/assets/authentication/auth-boxed.scss'])
        @vite(['resources/scss/dark/assets/authentication/auth-boxed.scss'])

        <style>
            #load_screen {
                display: none;
            }
        </style>
        <!--  END CUSTOM STYLE FILE  -->
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->

    <div class="auth-container d-flex">

        <div class="container mx-auto align-self-center">

            <div class="row">

                <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-8 col-12 d-flex flex-column align-self-center mx-auto">
                    <div class="card mt-3 mb-3">
                        <div class="card-body">

                            <div class="text-center mb-4">
                                <h2 class="mb-1">Iniciar sesión</h2>
                                <p class="text-muted mb-0">
                                    Ingresa con tu usuario para entrar al sistema
                                </p>
                            </div>

                            @if (session('status'))
                                <div class="alert alert-success" role="alert">
                                    {{ session('status') }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('login') }}" novalidate>
                                @csrf

                                {{-- Correo electrónico --}}
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        Correo electrónico
                                    </label>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        class="form-control @error('email') is-invalid @enderror"
                                        autocomplete="username"
                                        autocapitalize="none"
                                        spellcheck="false"
                                        autofocus
                                        required
                                    >

                                    @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                {{-- Contraseña --}}
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        Contraseña
                                    </label>

                                    <div class="input-group">
                                        <input
                                            type="password"
                                            id="password"
                                            name="password"
                                            class="form-control @error('password') is-invalid @enderror"
                                            autocomplete="current-password"
                                            required
                                        >

                                        <button
                                            class="btn btn-outline-secondary"
                                            type="button"
                                            id="toggle-password"
                                            aria-label="Mostrar contraseña"
                                            title="Mostrar contraseña"
                                        >
                                            <i class="fa-regular fa-eye" id="toggle-password-icon"></i>
                                        </button>
                                    </div>

                                    @error('password')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                {{-- Recordarme --}}
                                <div class="mb-4">
                                    <div class="form-check form-check-primary form-check-inline">
                                        <input
                                            class="form-check-input me-2"
                                            type="checkbox"
                                            id="remember"
                                            name="remember"
                                            value="1"
                                            {{ old('remember') ? 'checked' : '' }}
                                        >
                                        <label class="form-check-label" for="remember">
                                            Recordarme en este equipo
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <button
                                        type="submit"
                                        class="btn btn-primary w-100"
                                        id="btn-login"
                                    >
                                        <i class="fa-solid fa-right-to-bracket me-1"></i>
                                        Entrar
                                    </button>
                                </div>

                            </form>

                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!--  BEGIN CUSTOM SCRIPTS FILE  -->
    <x-slot:footerFiles>
        <script>
            (function () {
                const boton = document.getElementById('toggle-password');
                const input = document.getElementById('password');
                const icono = document.getElementById('toggle-password-icon');

                if (!boton || !input || !icono) return;

                boton.addEventListener('click', function () {
                    const visible = input.type === 'text';

                    input.type = visible ? 'password' : 'text';
                    icono.className = visible
                        ? 'fa-regular fa-eye'
                        : 'fa-regular fa-eye-slash';

                    boton.setAttribute(
                        'aria-label',
                        visible ? 'Mostrar contraseña' : 'Ocultar contraseña'
                    );
                    boton.setAttribute(
                        'title',
                        visible ? 'Mostrar contraseña' : 'Ocultar contraseña'
                    );
                });
            })();
        </script>
    </x-slot>
    <!--  END CUSTOM SCRIPTS FILE  -->

</x-base-layout>
