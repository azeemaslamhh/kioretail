@extends('backend.layout.main') @section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message') !!}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
   
    <div class="table-responsive">
        <table id="courier-table" class="table" style="width: 100%">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.name')}}</th>
                    <th>{{trans('file.Phone Number')}}</th>
                    <th>{{trans('file.Address')}}</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_courier_all as $key=>$courier)
                <tr data-id="{{$courier->id}}">
                    <td>{{$key}}</td>
                    <td>{{ $courier->name }}</td>
                    <td>{{ $courier->phone_number }}</td>
                    <td>{{ $courier->address }}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li>
                                    <button type="button" data="{{$courier->id}}" name_data="{{$courier->name}}" class="edit-btn btn btn-link view_report" data-toggle="modal" data-target="#editModal"><i class="fa fa-eye"></i> {{trans('file.view_report')}}</button></li>                                
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="tfoot active">
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tfoot>
        </table>
    </div>
</section>


<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
  <div role="document" class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h5 id="exampleModalLabel" class="modal-title"></h5>
              <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
              <input type="hidden" id="courier_id_db" name="courier_id_db" value="" />
              <input type="hidden" id="name_data" name="name_data" value="" />
          </div>
          <div class="modal-body">
            <p class="italic">
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="form-group mb-3">
                        <label>Packing Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" placeholder="Choose date"/>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group mb-3">
                        <label>Packing End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" placeholder="Choose date"/>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <select name="courier_status" id="courier_status" class="form-control">
                            <option value="">Select Status</option>
                            <option value="1">{{ trans('file.Pending') }}</option>
                            <option value="2">{{ trans('file.due') }}</option>
                            <option value="3">{{  trans('file.partial') }}</option>
                            <option value="4">{{ trans('file.paid') }}</option>
                        </select>    
                    </div>
                </div>
                <div class="col-md-12 form-group">
                    <input type="button" name="getReport" id="getReport" value="Get Report" class="btn btn-primary" />
                </div>
                

            </div>
              <div class="row">
                  <div class="col-md-12 form-group">
                      <label>{{trans('file.total_no_sale')}} *</label>
                      <input type="text" name="total_no_sale" readonly id="total_no_sale"  class="form-control">
                  </div>
                  <div class="col-md-12 form-group">
                      <label>{{trans('file.total_sale_amount')}} *</label>
                      <input type="text"  name="total_sale_amount" readonly id="total_sale_amount" class="form-control">
                  </div>
                  <div class="col-md-12 form-group">
                      <label>{{trans('file.total_product_cost')}} *</label>
                      <input type="text"  name="total_product_cost" readonly id="total_product_cost" class="form-control">
                  </div>
                  <div class="col-md-12 form-group">
                      <label>{{trans('file.total_shipping_cost')}} *</label>
                      <input type="text"  name="total_shipping_cost" readonly id="total_shipping_cost" class="form-control">
                  </div>
                  
                  <div class="col-md-12 form-group">
                      <label>{{trans('file.due_amount')}} *</label>
                      <input type="text"  id="due_amount"  readonly name="due_amount" class="form-control">
                  </div>
                  <div class="col-md-12 form-group">
                      <label>{{trans('file.paid_amount')}} *</label>
                      <input type="text"  id="paid_amount" readonly name="paid_amount" class="form-control">
                  </div>
                  <div class="col-md-12 form-group">
                      <label>{{trans('file.profit')}} *</label>
                      <input type="text"  id="profit" readonly name="profit" class="form-control">
                  </div>                  
              </div>                            
          </div>
      </div>
  </div>
</div>


@endsection

@push('scripts')
<script type="text/javascript">

$("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #courier-report-menu").addClass("active");

        $(document).on('click', '.edit-btn', function() {
            $("#editModal input[name='id']").val($(this).data('id'));
            $("#editModal input[name='name']").val($(this).data('name'));
            $("#editModal input[name='phone_number']").val($(this).data('phone_number'));
            $("#editModal input[name='address']").val($(this).data('address'));
        });

function confirmDelete() {
    if (confirm("Are you sure want to delete?")) {
        return true;
    }
    return false;
}

function getCourierData(){
    
   var courier_id =  $("#courier_id_db").val();
    
    $("#total_no_sale").val("");
    $("#total_sale_amount").val("");
    $("#paid_amount").val("");
    $("#due_amount").val("");
    $("#profit").val("");
    $("#total_product_cost").val("");
    $("#exampleModalLabel").html(' Report');
    var start_date = $("#start_date").val();
    var end_date =  $("#end_date").val();
    var courier_status =  $("#courier_status").val();            
    var name_data =  $("#name_data").val();            

    $.ajax({
        type:'POST',
        url:"{{ route('getCourierReport') }}",
        data:{
            "courier_id": courier_id,
            "start_date": $("#start_date").val(),
            "end_date": $("#end_date").val(),
            "courier_status": courier_status,
            '_token':token
        },
        dataType:'json',
        success:function(data){                
            if(data.data.totalSaleCount!=""){
            $("#total_no_sale").val(data.data.totalSaleCount);
            }
            if(data.data.totalSaleamount!=""){
            $("#total_sale_amount").val(data.data.totalSaleamount);
            }
            if(data.data.paid_amount!=""){
            $("#paid_amount").val(data.data.paid_amount);
            }
            if(data.data.due_amount!=""){
            $("#due_amount").val(data.data.due_amount);
            }
            if(data.data.profit!=""){
            $("#profit").val(data.data.profit);
            }
            if(data.data.product_cost!=""){
                $("#total_product_cost").val(data.data.product_cost);
            }
            if(data.data.product_cost!=""){
                $("#total_shipping_cost").val(data.data.shipping_cost);
            }

            
            
            $("#exampleModalLabel").html(name_data+' Report');
        
        }
    });
}
$(document).ready(function(){
    var token = "{{ csrf_token() }}";
    
    $("#start_date").val("");
    $("#end_date").val("");
    $("#courier_status").val("");

    $(document).on('click', '.view_report', function() {
            var courier_id  = $(this).attr('data');
            $("#courier_id_db").val(courier_id);
            $("#start_date").val("");
            $("#end_date").val("");
            var name_data  = $(this).attr('name_data');
            $("#name_data").val(name_data);            
           getCourierData();
        });               
});
$(document).on('click', '#getReport', function() {           
            getCourierData();
});
    var table = $('#courier-table').DataTable( {
        responsive: true,
        fixedHeader: {
            header: true,
            footer: true
        },
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{trans("file.records per page")}}',
             "info":      '<small>{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search":  '{{trans("file.Search")}}',
            'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        'columnDefs': [
            {
                "orderable": false,
                'targets': [0,4]
            },
            {
                'render': function(data, type, row, meta){
                    if(type === 'display'){
                        data = '';
                    }
                    $('.dt-buttons').hide();
                   return data;
                },
                'checkboxes': {
                   'selectRow': false,
                   'selectAllRender': ''
                },
                'targets': [0]
            }
        ],
        'select': { style: 'multi',  selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip'        
    } );

</script>
@endpush
