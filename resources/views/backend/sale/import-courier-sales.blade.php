@extends('backend.layout.main') @section('content')
@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>Import courier Fee</h4>
                    </div>
                    <div class="card-body">                        
                        <form method="POST" action="{{ route('importCSV') }}" enctype="multipart/form-data" accept-charset="UTF-8">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-body" data-name="TLLaBguC">
                                    @csrf
                                    
                                    <div class="form-group row" data-name="cojwsdasdyCSi">
                                        <label class="col-form-label col-md-12">Courier</label>
                                        <div class="col-md-8" data-name="RoEzuSdsadwG">
                                            <select name="courier" id="courier" class="form-control">
                                                
                                                <option value="">Select Courier</option>
                                                @foreach($couriersData as $couriersRow)
                                                <option value="{{ $couriersRow->id }}">{{ $couriersRow->name }}</option>
                                                @endforeach
                                                
                                            </select>
                                        </div>
                                        
                                    </div>
                                    <div class="form-group row" data-name="cojwsdasdyCSi">
                                        <label class="col-form-label col-md-12">Fuel_surcharge</label>
                                        <div class="col-md-8" data-name="RoEzuSdsadwG">
                                            <input required="" type="text" name="fuel_surcharge" id="fuel_surcharge" class="form-control" />                                            
                                        </div>
                                        
                                    </div>
                                    <div class="form-group row" data-name="cojwydasdCSi">
                                        <label class="col-form-label col-md-12">Fuel Factor</label>
                                        <div class="col-md-8" data-name="RoEasdzuSwG">
                                            <input required="" type="text" name="fuel_factor" id="fuel_factor" class="form-control" />                                            
                                        </div>                                        
                                    </div>
                                    <div class="form-group row" data-name="cojasdwyCSi">
                                        <label class="col-form-label col-md-12">G.S.T.</label>
                                        <div class="col-md-8" data-name="RoEzuSsaswG">
                                            <input required="" type="text" name="gst" id="gst" class="form-control" />                                            
                                        </div>                                        
                                    </div>
                                    <div class="form-group row" data-name="cojwdasdyCSi">
                                        <label class="col-form-label col-md-12">Insurance</label>
                                        <div class="col-md-8" data-name="RoEzuSasawG">
                                            <input required="" type="text" name="insurance" id="insurance" class="form-control" value="0" />                                            
                                        </div>                                        
                                    </div>
                                    
                                    <div class="form-group row" data-name="cojwyCSdsdi">
                                        <label class="col-form-label col-md-12">Import Courier Fee File</label>
                                        <div class="col-md-8" data-name="RoEzuSwG">
                                            <input required="" type="file" name="csv_file" id="csv_file" class="form-control"  />
                                            
                                        </div>
                                        
                                    </div>                                    
                                    <div class="kt-portlet__foot" data-name="BeefdcDD">
                                        <div class="" data-name="nXYQNpgi" style="padding-top: 16px;  font-size: 13px;">
                                            <div class="col-md-12 col-sm-12 action-buttons" data-name="veSimjmL">
                                                <button type="submit" name="btn" class="btn btn-success" >{{trans('Submit')}}</button>
                                            </div>
                                            <div class="row" id="Message" style="display: none;">
                                                <div id="successID" class="alert alert-success alert-dismissible text-center"></div>
                                                <div id="errorID" class="alert alert-danger alert-dismissible text-center"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>                            
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
</section>


@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#sale").siblings('a').attr('aria-expanded', 'true');
    $("ul#sale").addClass("show");
    $("ul#sale #import_couriers-fee-menu").addClass("active");
    function runcron(cron) {
        $("#Message").hide();
        $.ajax({
            url: "{{ URL::route('run.cron.manually') }}",
            type: 'POST',
            dataType: 'json',
            data: {cron: cron, _token: token},
            success: function (result) {
                if (result.status == 'success') {
                    $("#errorID").hide();
                    $("#successID").html(result.message);
                    $("#successID").show();
                } else {
                    $("#successID").hide();
                    $("#errorID").html(result.message);
                    $("#errorID").show();

                }
                $("#Message").show();
            }
        });
    }
</script>
@endpush
