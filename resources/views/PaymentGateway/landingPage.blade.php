
<script type="text/javascript">
var baseUrl = <?php echo "'".URL::to('/')."/'";?>;
</script>

<script src="{{asset('js/core/jquery.js')}}"></script>
<script src="{{asset('js/paymentGateway/paymentGateway.js')}}"></script>
<div class="container">
    <div class="col-lg-5 col-sm-8 ml-auto mr-auto">
        <form method="POST" autocomplete="off">

            <input type="hidden" id="gatewayName" name="gatewayName" value="{{$pgResponseData['gatewayName']}}">
            <input type="hidden" id="pgTxnId" name="pgTxnId" value="{{$pgResponseData['pgTxnId']}}">
            <input type="hidden" id="postData" name="postData" value="{{ json_encode($pgResponseData) }}">
            <div className="mb-3 text-center">
            <img src="{{URL::asset('/images/flight_loader.png')}}" alt="Please wait..." class="preloader" />
            </div>
            
            <h3 class="text-center"><b class="loader-text">Please wait.. Don't Refresh the page</b></h3>
            <h3 class="text-center"><b class="loader-text">Your PG Transaction Id is : {{$pgResponseData['pgTxnId']}}</b></h3>

        </form>           

        

    </div>
</div>