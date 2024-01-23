@extends('backend.layout.main') @section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('not_permitted') !!}</div>
@endif
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('Cron Settings')}}</h4>
                    </div>
                    <div class="card-body">                        
                        {!! Form::open(['route' => 'setting.crons', 'method' => 'post']) !!}
                            <div class="row">
                                <div class="col-md-6">
                                <div class="form-body" data-name="TLLaBguC">
                            
                           
                            <div class="form-group row" data-name="cojwyCSi">
                                <label class="col-form-label col-md-12">Sync Products</label>
                                <div class="col-md-8" data-name="RoEzuSwG">
                                    <select class="form-control m-select2" name="sync_products">
                                     <?php $trigger_cron = \App\Models\UserCronSetting::getCronTime("sync_products"); ?>
                                        @foreach ($time_2 as $time_value => $time_name) {
                                        <option value="{{$time_value}}" {{(isset($trigger_cron) && $trigger_cron == $time_value) ? 'selected' : ''}}>{{ $time_name }}</option>
                                        }
                                        @endforeach
                                    </select>
                                    <!-- <small><span class="help-block">{{trans('app.settings.cron.trigger_scheduling_note')}}</span></small> -->
                                </div>
                                <button type="button" class="btn btn-success btn-run" style="margin-right:10px"  onclick="runcron('sync:products');"><i class="fa fa-angle-right" ></i> {{trans('Run Now')}}</button> 
                                <!-- <button type="button" class="btn btn-warning btn-run" onclick="runcron('trigger:processing --force');"><i class="fa fa-angle-right"></i> Force Run </button>  -->
                            </div>
                            <div class="form-group row" data-name="jWDSQEXc">
                                <label class="col-form-label col-md-12">Sync Orders</label>
                                <div class="col-md-8" data-name="jEFadLqJ">
                                    <select class="form-control m-select2" name="sync_orders">
                                    <?php $bounce_process_cron = \App\Models\UserCronSetting::getCronTime("sync_orders"); ?>
                                        @foreach ($time_2 as $time_value => $time_name) {
                                        <option value="{{$time_value}}" {{(isset($bounce_process_cron) && $bounce_process_cron == $time_value) ? 'selected' : ''}}>{{ $time_name }}</option>
                                        }
                                        @endforeach
                                    </select>
                                    <!-- <small><span class="help-block">{{trans('app.settings.cron.bounce_processing_note')}}</span></small> -->
                                </div>
                                <button type="button" class="btn btn-success btn-run" style="margin-right:10px"  onclick="runcron('sync:orders');"><i class="fa fa-angle-right"></i> {{trans('Run Now')}}</button>                                 
                            </div>
                            <div class="form-group row" data-name="jWDSQEXc">
                                <label class="col-form-label col-md-12">Check Orders</label>
                                <div class="col-md-8" data-name="jEFadLqJ">
                                    <select class="form-control m-select2" name="check:orders">
                                    <?php $bounce_process_cron = \App\Models\UserCronSetting::getCronTime("check:orders"); ?>
                                        @foreach ($time_1 as $time_value => $time_name) {
                                        <option value="{{$time_value}}" {{(isset($bounce_process_cron) && $bounce_process_cron == $time_value) ? 'selected' : ''}}>{{ $time_name }}</option>
                                        }
                                        @endforeach
                                    </select>
                                    <!-- <small><span class="help-block">{{trans('app.settings.cron.bounce_processing_note')}}</span></small> -->
                                </div>
                                <button type="button" class="btn btn-success btn-run" style="margin-right:10px"  onclick="runcron('check:orders');"><i class="fa fa-angle-right"></i> {{trans('Run Now')}}</button>                                 
                            </div>
                            <div class="kt-portlet__foot" data-name="BeefdcDD">
                        <div class="" data-name="nXYQNpgi" style="padding-top: 16px;  font-size: 13px;">
                            <div class="col-md-12 col-sm-12 action-buttons" data-name="veSimjmL">
                                <button type="submit" name="btn" class="btn btn-success" value="">{{trans('Submit')}}</button>
                            </div>
                            <div class="row" id="Message" style="display: none;">
                                <div id="successID" class="alert alert-success alert-dismissible text-center"></div>
                                <div id="errorID" class="alert alert-danger alert-dismissible text-center"></div>
                            </div>
                        </div>
                    </div>
                                </div>
                            </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#setting").siblings('a').attr('aria-expanded','true');
    $("ul#setting").addClass("show");
    $("ul#setting #create-sms-menu").addClass("active");    
    function runcron(cron) {
        $("#Message").hide();
        $.ajax({
            url: "{{ URL::route('run.cron.manually') }}",
            type: 'POST',
            dataType:'json',
            data: {cron: cron,_token: token},
            success: function(result) {
                if(result.status=='success'){
                    $("#errorID").hide();
                    $("#successID").html(result.message);
                    $("#successID").show();
                }else{
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
