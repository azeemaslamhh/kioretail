@if (session()->has('message'))
    <div class="alert alert-{{session('type')}} alert-dismissible fade show text-center" role="alert">
        <strong>{{ session('message')}}</strong>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif
