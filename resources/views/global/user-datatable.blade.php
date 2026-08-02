@push('scripts')
    {{ $dataTable->scripts() }}
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                <div class="header-title">
                    <h4 class="card-title">{{ $pageTitle ?? 'List'}}</h4>
                </div>
                    <div class="card-action">
                        {!! $headerAction ?? '' !!}
                        <button id="exportToggle" class="float-right btn btn-sm border-radius-10 btn-danger ml-2"
                            type="button" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="20" height="20" fill="currentColor">
                                <path d="M320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64zM308.7 451.3L204.7 347.3C200.1 342.7 198.8 335.8 201.2 329.9C203.6 324 209.5 320 216 320L272 320L272 224C272 206.3 286.3 192 304 192L336 192C353.7 192 368 206.3 368 224L368 320L424 320C430.5 320 436.3 323.9 438.8 329.9C441.3 335.9 439.9 342.8 435.3 347.3L331.3 451.3C325.1 457.5 314.9 457.5 308.7 451.3z"/>
                            </svg>
                            {{ __('message.export') }}
                        </button>
                    </div>
                </div>
                <div class="card-body px-0">
                <div class="table-responsive">
                        {{ $dataTable->table(['class' => 'table table-striped w-100'],true) }}
                </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">{{ __('message.export') }}</h5>
                    <a href="javascript:void(0);" data-bs-dismiss="modal" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg"width="25"height="25"viewBox="0 0 24 24"fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.59 13.41L15.41 16 12 12.59 8.59 16 7.41 14.41 10.83 11 7.41 7.59 8.59 6.41 12 9.83 15.41 6.41 16.59 7.59 13.17 11 16.59 14.41z"/>
                        </svg>
                    </a>
                </div>
                <div class="modal-body">
                    <form id="exportForm" method="get">
                        <div class="form-group mb-3">
                            <label for="date_range" class="d-flex mb-2">{{ __('message.select_date') }}</label>
                            <input type="text" class="form-control" id="date_range" name="date_range"  placeholder="{{ __('message.select_date_range') }}">

                            <input type="hidden" id="from_date" name="from_date" value="{{ request('from_date') }}">
                            <input type="hidden" id="to_date" name="to_date" value="{{ request('to_date') }}">
                        </div>
                        <div class="d-flex justify-content-center mb-3">
                            <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                <label class="btn btn-outline-primary">
                                    <input class="form-check-input" type="radio" name="duration" value="3months">
                                    {{ __('message.three_month') }}
                                </label>
                                <label class="btn btn-outline-primary">
                                    <input class="form-check-input" type="radio" name="duration" value="6months">
                                    {{ __('message.six_month') }}
                                </label>
                                <label class="btn btn-outline-primary">
                                    <input class="form-check-input" type="radio" name="duration" value="1year">
                                    {{ __('message.one_year') }}
                                </label>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center mb-3">
                            <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                <label class="btn btn-secondary">
                                    <input class="form-check-input" type="radio" name="options" value="xls" checked> {{ __('message.xls') }}
                                </label>
                                <label class="btn btn-secondary">
                                    <input class="form-check-input" type="radio" name="options" value="ods"> {{ __('message.ods') }}
                                </label>
                                <label class="btn btn-secondary">
                                    <input class="form-check-input" type="radio" name="options" value="csv"> {{ __('message.csv') }}
                                </label>
                                <label class="btn btn-secondary">
                                    <input class="form-check-input" type="radio" name="options" value="pdf"> {{ __('message.pdf') }}
                                </label>
                                <label class="btn btn-secondary">
                                    <input class="form-check-input" type="radio" name="options" value="html"> {{ __('message.html') }}
                                </label>
                            </div>
                        </div>
                        <hr>
                        <h6 class="mb-3">{{ __('message.select_columns') }}</h6>
                        <div class="row">
                            @foreach (['id','name','phone_number','email','age','status','created_at'] as $column)
                                <div class="col-auto">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="columns[]" value="{{ $column }}" id="col_{{ $column }}" checked>
                                        <label class="form-check-label" for="col_{{ $column }}">{{ __('message.' . $column) }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('message.cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="downloadBtn">{{ __('message.download') }}</button>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            $(document).ready(function() {

                $('.select2').select2({
                    dropdownParent: $('#filterModal'),
                });

                flatpickr("#date_range", {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    onChange: function(selectedDates) {
                        if (selectedDates.length === 2) {
                            var fromDate = selectedDates[0].toISOString().split('T')[0];
                            var toDate = selectedDates[1].toISOString().split('T')[0];

                            $('#from_date').val(fromDate);
                            $('#to_date').val(toDate);
                        }
                    }
                });
                $('input[name="duration"]').on('change', function() {
                    var today = new Date();
                    var fromDate = new Date();
                    var toDate = today.toISOString().split('T')[0];

                    if (this.value === '3months') {
                        fromDate.setMonth(today.getMonth() - 3);
                    } else if (this.value === '6months') {
                        fromDate.setMonth(today.getMonth() - 6);
                    } else if (this.value === '1year') {
                        fromDate.setFullYear(today.getFullYear() - 1);
                    }

                    var from = fromDate.toISOString().split('T')[0];
                    $('#from_date').val(from);
                    $('#to_date').val(toDate);

                    $('#date_range').val('');
                });

                // download report
                $('#downloadBtn').on('click', function() {
                    var fileType = $('input[name="options"]:checked').val();
                    var columns = $('input[name="columns[]"]:checked').map(function() {
                        return $(this).val();
                    }).get();

                    var pdfRoute = "{{ route('download.user.report.pdf') }}";
                    var generalRoute = "{{ route('download.user.report', ':fileType') }}";

                    var url = fileType === 'pdf' ?
                        pdfRoute :
                        generalRoute.replace(':fileType', fileType);

                    var fromDate = $('#from_date').val();
                    var toDate = $('#to_date').val();
                    if (!fromDate || !toDate) {
                        alert("Please select a date range or duration before downloading.");
                        return;
                    }

                    var queryString = $.param({
                        columns: columns,
                        from_date: fromDate,
                        to_date: toDate,
                    });

                    window.location.href = url + '?' + queryString;

                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                });
            });
        </script>
    @endpush
</x-app-layout>
