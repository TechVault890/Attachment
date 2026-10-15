<?php
function home_index(): void {
    if (current_user()) {
        redirect('dashboard/index');
    }
    view('home/index', ['title' => 'Welcome']);
}
