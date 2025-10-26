<?php

namespace PCP_AI_Addon\Services;

/**
 * Simple service registry to bootstrap components.
 */
class Service_Registry {

    /**
     * Registered services.
     *
     * @var array
     */
    protected $services = array();

    /**
     * Register a service; call its hooks if available.
     *
     * @param object $service Service instance.
     */
    public function register( $service ) {
        $this->services[] = $service;

        if ( method_exists( $service, 'register_hooks' ) ) {
            $service->register_hooks();
        }
    }

    /**
     * Return registered services (useful for testing).
     *
     * @return array
     */
    public function all() {
        return $this->services;
    }
}



