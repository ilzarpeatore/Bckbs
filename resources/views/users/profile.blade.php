@push('scripts')
    {{ in_array($type, ['subscription']) ? $dataTable->scripts() : '' }}
    <script>
        function getAssignList(type = ''){
            url = "{{ route('get.assigndietlist') }}";
            if( type == 'workout' ) {
                url = "{{ route('get.assignworkoutlist') }}";
            }
            
            $.ajax({
                type: 'get',
                url: url,
                data: {
                    'user_id': "{{ $data->id }}",
                },
                success: function(res){
                    $('#'+type+'-data').html(res.data);
                }
            });
        }
        $(document).ready(function () {
            @if ($type == 'detail')
            getAssignList('diet');
            getAssignList('workout');
            @endif

            @if($type == 'matrix')
                // Weight Chart
                let weight_chart_options = generateChartOptions( "{{__('message.weight')}}" , [], []);
                let weightChart = createChart('#apex-line-area-weight', weight_chart_options);
                weightChart.render();

                ajaxForChart(weightChart, 'week', 'weight', 'kg' )

                // create Heart chart
                let heart_rate_chart_option = generateChartOptions("{{__('message.heart_rate')}}", [], []);
                let heartChart = createChart('#apex-line-area-heart', heart_rate_chart_option);
                heartChart.render();

                ajaxForChart(heartChart, 'week', 'heart-rate', 'bpm' )

                // create Push Up chart
                let pushups_chart_option = generateChartOptions( "{{__('message.push_up')}}", [], []);
                let pushUpsChart = createChart('#apex-line-area-push-ups', pushups_chart_option);
                pushUpsChart.render();
                ajaxForChart(pushUpsChart, 'week', 'push-up-min', 'Reps' )

                // Weight Chart Ajax
                $(document).on('change','#weight-overview', function() {
                    let weight_value = $('#weight-overview :selected').val();
                    ajaxForChart(weightChart, weight_value, 'weight', 'kg');
                });

                // Heart Chart Ajax
                $(document).on('change','#heart-rate-overview', function() {
                    let heart_value = $('#heart-rate-overview :selected').val();
                    ajaxForChart(heartChart, heart_value, 'heart-rate', 'bpm');
                });

                // Push Ups Chart Ajax
                $(document).on('change','#push-up-overview', function() {
                    let pushup_value = $('#push-up-overview :selected').val();
                    ajaxForChart(pushUpsChart, pushup_value, 'push-up-min', 'Reps');
                });

            @endif
            @if($type == 'daily-step-water-tracking')

                @php
                    $stepDates  = $tracking['step_graph']->pluck('date');
                    $stepValues = $tracking['step_graph']->pluck('value');
                    $stepGoalValues = $tracking['step_graph']->pluck('goal_value');
                    
                    $waterDates  = $tracking['water_graph']->pluck('date');
                    $waterValues = $tracking['water_graph']->pluck('value');
                    $waterGoalValues = $tracking['water_graph']->pluck('goal_value');
                @endphp
            
                $('#step-overview').on('change', function () {
                    var type = $(this).val();
                    var step_value = 'step_value';

                    $.ajax({
                        url: "{{ route('users.show', ['id' => $data->id, 'tab' => $type]) }}",
                        type: "GET",
                        dataType: "json",
                        data: {
                            type: type,
                            step_value: step_value
                        },
                        success:function(res){
                            updateBarChart(
                                stepGoalChart,
                                res.dates,
                                res.values,
                                res.goal_value,
                                "{{ __('message.achieved') }}"
                            );
                        },
                        error:function(err){
                            console.log(err);
                        }
                    });
                });
                $('#water-overview').on('change', function () {
                    var type = $(this).val();
                    var water_value = 'water_value';

                    $.ajax({
                        url: "{{ route('users.show', ['id' => $data->id, 'tab' => $type]) }}",
                        type: "GET",
                        dataType: "json",
                        data: {
                            type: type,
                            water_value: water_value
                        },
                        success:function(res){
                            updateBarChart(
                                waterGoalChart,
                                res.dates,
                                res.values,
                                res.goal_value,
                                "{{ __('message.consumed') }}"
                            );
                        },

                        error:function(err){
                            console.log(err);
                        }
                    });
                });

                let stepGoalChart = null;
                let waterGoalChart = null;

                function generateBarColumnChartOptions(series, categories, yAxisTitle) {                
                    return {
                        series: series,
                        chart: {
                            type: 'bar',
                            height: 350,
                            toolbar: { show: false }
                        },
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '30%',
                                borderRadius: 5,
                                dataLabels: {
                                    position: 'top'
                                },
                            },
                        },
                        dataLabels: {
                            enabled: true,
                            offsetY: -25,
                            style: {
                                colors: ['#BDBDBD', '#F16A1B']
                            }
                        },
                        stroke: {
                            show: true,
                            width: 2,
                            colors: ['transparent']
                        },
                        xaxis: {
                            categories: categories,                        
                        },
                        yaxis: {
                            title: {
                                style: {
                                    color: "#F16A1B"
                                },
                                text: yAxisTitle
                            },
                            min: 0,
                            tickAmount: 4,
                            max: function (max) {
                                return max + Math.ceil(max * 0.1);
                            }
                        },
                        tooltip: {
                            shared: false,
                            intersect: true
                        },
                        colors: ['#BDBDBD','#F16A1B'],
                        legend: {
                            position: 'bottom',
                            onItemClick: {
                                toggleDataSeries: false
                            },
                            onItemHover: {
                                highlightDataSeries: false
                            }
                        }
                    };
                }

                function renderChart(container, chartRef, options) {
                    if (chartRef) {
                        chartRef.destroy();
                    }
                    chartRef = new ApexCharts(
                        document.querySelector(container),
                        options
                    );
                    chartRef.render();
                    return chartRef;
                }
                const series = (label, c, g) => ([{
                        name: label,
                        data: c
                    },
                    {
                        name: "{{ __('message.goal') }}",
                        data: g
                    }
                ]);

                stepGoalChart = renderChart('#apex-line-area-step-goal',stepGoalChart,generateBarColumnChartOptions(series("{{ __('message.achieved') }}", @json($stepValues), @json($stepGoalValues)), @json($stepDates), "{{ __('message.steps') }}"));
                waterGoalChart = renderChart('#apex-line-area-water-goal',waterGoalChart,generateBarColumnChartOptions(series("{{ __('message.consumed') }}", @json($waterValues), @json($waterGoalValues)), @json($waterDates), "{{ __('message.water') }}"));
            @endif
        });

        function ajaxForChart(chart, dateValue = 'week', type, unit)
        {
            $.ajax({
                type: 'get',
                url: "{{ route('user.fetchGraph') }}",
                data: { 
                    type: type,
                    unit: unit,
                    dateValue: dateValue,
                    id: {{ $data->id }}
                },
                dataType : 'json',
                success: function (response) {
                    let data = response.data;
                    let category = response.category;
                    
                    updateChart(chart,data, category);
                }
            });
        }

        function updateChart(chart, data, categories)
        {
            chart.updateOptions({
                xaxis: {
                    categories: categories
                },
                series: [{
                    data: data
                }]
            });

            chart.updateSeries([
                {
                    data: data
                }
            ]);
        }

        // Function to update chart data and categories
        function updateBarChart(chart, dates, consumedValues, goalValues, primaryLabel)
        {
            if (!chart) return;

            chart.updateOptions({
                xaxis: {
                    categories: dates
                }
            });

            chart.updateSeries([
                {
                    name: primaryLabel,
                    data: consumedValues
                },
                {
                    name: "{{ __('message.goal') }}",
                    data: goalValues
                }
            ]);
        }
        function generateChartOptions(seriesName, yAxisData, xAxisData) {
            let totalXAxis = xAxisData.length;
            let maxLabels = 10;
            let step = Math.ceil(totalXAxis / maxLabels);

            return {
                series: [{
                    name: seriesName,
                    data: yAxisData
                }],
                chart: {
                    height: 350,
                    type: 'line',
                    toolbar: {
                        show: false
                    }
                },
                stroke: {
                    curve: 'smooth',
                },
                xaxis: {
                    labels: {
                        rotate: -45,
                        step: step
                    },
                    categories: xAxisData,
                    tickPlacement: 'on'
                },
                colors: ['#F16A1B', '#EC7E4A'],
            };
        }
        // Function to create a new ApexCharts instance
        function createChart(elementId, options) {
            return new ApexCharts(document.querySelector(elementId), options);
        }
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch border-radius-20">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h5 class="font-weight-bold">{{ $pageTitle  }}</h5>
                            <div>
                                <a href="{{ route('users.index') }}" class="float-end ms-1 btn btn-sm btn-primary">{{ __('message.back') }}</a>
                                @if(auth()->user()->can('users-edit'))
                                    <a href="{{ route('users.edit', $data->id) }}" class="float-end btn btn-sm btn-primary"></i> {{ __('message.edit') }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<div class="row">
    <div class="col-lg-12">
    <ul class="nav nav-payment-tabs" id="tab-text" role="tablist">
        <li class="nav-item">
            <a href="{{ route('users.show', [$data->id]) }}" id="pills-detail-tab-fill" role="tab" class="nav-link {{ 'detail' == $type ? 'active' : '' }}" aria-controls="pills-detail" aria-selected="{{ 'detail' == $type ? true : false }}"> {{ __('message.detail_form_title', ['form' => __('message.profile')]) }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('users.show', [$data->id  , 'subscription']) }}" id="pills- subscription-tab-fill" role="tab" class="nav-link {{ 'subscription' == $type ? 'active' : '' }}" aria-controls="pills-subscription" aria-selected="{{ 'subscription' == $type ? true : false }}"> {{ __('message.subscription') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('users.show', [$data->id  , 'matrix']) }}" id="pills- matrix-tab-fill" role="tab" class="nav-link {{ 'matrix' == $type ? 'active' : '' }}" aria-controls="pills-matrix" aria-selected="{{ 'matrix' == $type ? true : false }}"> {{ __('message.matrix') }}</a>
        </li> 
        <li class="nav-item">
            <a href="{{ route('users.show', [$data->id  , 'daily-step-water-tracking']) }}" id="pills- daily-step-water-tracking-tab-fill" role="tab" class="nav-link {{ 'daily-step-water-tracking' == $type ? 'active' : '' }}" aria-controls="pills-daily-step-water-tracking" aria-selected="{{ 'daily-step-water-tracking' == $type ? true : false }}"> {{ __('message.daily_tracking') }}</a>
        </li> 
    </ul>
<div class="tab-content">
@if ($type == 'detail')
    <div class="row">
        <div class="col-lg-6">
            <div class="profile-content tab-content">
                <div id="profile-profile" class="">
                    <div class="card">
                        <div class="card-header">
                            <div class="header-title">
                                <h4 class="card-title">{{__('message.profile')}}</h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-start align-items-center">
                                <div class="pe-3">
                                    <img src="{{ getSingleMedia($data, 'profile_image') }}" class="rounded-pill avatar-130 img-fluid"  alt="user-img">
                                </div>
                                <div class="pe-3">
                                    <p class="m-0">{{ $data->display_name }}</p>
                                    <p class="m-0">{{ $data->email }}</p>
                                    <p class="m-0">{{ $data->phone_number }}</p>
                                    <p class="m-0">{{ $data->gender }}</p>
                                </div>
                            </div>
                            <p></p>
                            <div class="d-flex justify-content-between align-items-center flex-wrap  mb-2">
                                <div>
                                    <span>{{ __('message.weight') }}</span>
                                    <p>{{ optional($data->userProfile)->weight ?? '-' }} {{ optional($data->userProfile)->weight_unit }}</p>
                                </div>
                                <div>
                                    <span>{{ __('message.height') }}</span>
                                    <p>{{ optional($data->userProfile)->height ?? '-' }} {{ optional($data->userProfile)->height_unit }}</p>
                                </div>
                                <div>
                                    <span>{{ __('message.age') }}</span>
                                    <p>{{ optional($data->userProfile)->age ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="row">
                <div class="col-6">
                    <div class="card">
                        <div class="card-body" style="height: 145px">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="rounded p-3">
                                    <svg width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M16.0836 6.6123C14.8299 6.94223 14.0876 7.56084 12.9329 9.23521C8.42938 15.7512 5.55078 22.5064 3.92591 30.3256C3.68671 31.4886 3.65372 31.7938 3.65372 32.8826C3.66197 34.0291 3.67846 34.161 3.89291 34.7219C4.45378 36.2148 5.30334 37.1716 6.8045 38.0211C17.1806 43.8608 29.6271 44.8176 40.8692 40.6275C44.2757 39.3573 45.0098 38.9284 45.5707 37.8562L45.8016 37.419L45.8264 30.3504C45.8429 23.3972 45.8429 23.2818 45.6779 22.8364C45.4222 22.1518 45.0263 21.6981 44.2675 21.2115C42.5106 20.0897 40.7043 19.5041 38.6587 19.4216C36.0358 19.3062 33.5614 20.1475 31.5159 21.8548C31.1282 22.1765 30.988 22.3497 30.955 22.5642C30.856 23.0591 31.1612 23.4302 31.6643 23.4385C31.9035 23.4385 32.085 23.3395 32.6954 22.8611C33.6109 22.1353 34.9718 21.4507 36.0688 21.1537C36.8112 20.9558 37.0339 20.931 38.2546 20.9393C39.4588 20.9393 39.7063 20.964 40.4238 21.1537C42.0405 21.5826 43.7148 22.4652 44.1272 23.1003L44.3582 23.4715V30.1772C44.3582 36.6025 44.3499 36.8994 44.2015 37.1963C44.119 37.3613 43.9788 37.5675 43.8881 37.6417C43.6736 37.8397 42.5766 38.3428 41.1002 38.9284C30.8478 42.9865 19.7128 42.6153 9.83156 37.8727C7.0107 36.52 6.09516 35.8601 5.56728 34.8044C5.00641 33.6826 4.96517 32.8496 5.39407 30.7298C6.8045 23.6117 9.51813 17.0709 13.5597 11.025C14.7474 9.24345 15.0361 8.87229 15.4733 8.53412C15.9599 8.16295 16.859 7.89901 17.4116 7.965C18.0137 8.03098 22.5337 9.30119 22.9791 9.51564C23.4822 9.77133 24.1008 10.4724 24.3235 11.0498C24.6039 11.7839 24.571 12.4025 24.1998 13.5407C23.8204 14.679 23.6224 15.0501 23.1358 15.4955C22.6904 15.9079 22.2862 16.1224 21.6924 16.2626C21.1892 16.3781 20.9335 16.642 20.9335 17.0627C20.9335 17.4586 21.2387 17.7473 21.6594 17.7473C22.7646 17.7473 24.1668 16.8812 24.9091 15.7347C25.2968 15.1326 25.9319 13.2273 26.0061 12.4272C26.1298 11.1653 25.6432 9.9033 24.6947 8.96302C23.9441 8.22069 23.5482 8.03923 21.5356 7.47011C17.5436 6.35661 17.2466 6.30712 16.0836 6.6123Z" fill="#EC7E4A" stroke="#EC7E4A" stroke-width="0.714286" />
                                        <path d="M17.791 14.7698L17.5518 15.009V17.005C17.5518 19.2567 17.6343 20.6424 17.9312 23.1664C18.2694 26.1027 18.1869 26.9193 17.4363 28.4617C17.0239 29.3112 17.0074 29.5339 17.3621 29.8473C17.6096 30.0783 18.0302 30.0948 18.3106 29.8803C18.5498 29.6906 19.1355 28.4781 19.3746 27.6863C19.6633 26.7295 19.6716 25.2531 19.4159 23.0261C19.119 20.4775 19.0365 19.1 19.0365 16.8565V14.9347L18.8303 14.7368C18.5416 14.4398 18.1044 14.4563 17.791 14.7698Z" fill="#EC7E4A" stroke="#EC7E4A" stroke-width="0.714286" />
                                        <path d="M23.4491 24.2963C23.0862 24.3541 22.5748 24.4696 22.3109 24.5438C21.915 24.6593 21.816 24.7253 21.7005 24.9562C21.5521 25.2861 21.6015 25.5913 21.849 25.8387C22.0469 26.0367 22.3686 26.0285 23.7378 25.7893C24.6863 25.616 26.5752 25.6573 27.5319 25.8635C29.6517 26.3254 31.7055 27.3811 33.5366 28.9648C34.1799 29.5174 34.3531 29.6246 34.5923 29.6246C35.013 29.6246 35.2852 29.3195 35.2852 28.8658C35.2852 28.5441 35.2439 28.4781 34.815 28.074C33.1737 26.5233 30.6992 25.1294 28.6454 24.5933C27.0948 24.1891 24.942 24.0654 23.4491 24.2963Z" fill="#EC7E4A" stroke="#EC7E4A" stroke-width="0.714286" />
                                    </svg>
                                </div>
                                <div class="text-end">
                                    {{__('message.bmi')}}
                                    <h2>{{ optional($data->userProfile)->bmi ?? '-'}}</h2>
                                    {{ __('message.kcal') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6">
                    <div class="card">
                        <div class="card-body" style="height: 145px">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="rounded p-3">
                                    <svg width="50" height="50" viewBox="0 0 50 50" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path d="M22.5158 10.167C15.1221 10.9357 8.53372 15.2548 4.67217 21.8432C1.8904 26.6381 0.95704 32.952 2.21982 38.5155C2.73225 40.7666 0.0785839 40.5286 24.9865 40.5286C49.8943 40.5286 47.2407 40.7666 47.7531 38.4972C49.1806 32.2382 47.9544 25.723 44.2942 20.2144C43.1229 18.4575 39.9202 15.2548 38.1633 14.0835C34.7593 11.8324 31.2454 10.5697 27.1826 10.1487C25.2244 9.94742 24.5838 9.94742 22.5158 10.167ZM30.1657 13.3148C37.3397 15.2181 43.0314 20.8732 45.0262 28.0839C45.6302 30.2251 45.8498 34.3429 45.4837 36.4658L45.2641 37.7835H24.9865H4.70878L4.48916 36.4658C4.14144 34.3978 4.34275 30.4081 4.91009 28.212C6.92322 20.5804 13.0541 14.7606 20.8687 13.0769C23.3027 12.5462 27.6584 12.656 30.1657 13.3148Z" fill="#EC7E4A" />
                                        <path d="M24.4925 16.5541C23.8519 16.8104 23.7055 17.3228 23.7055 19.1895C23.7055 20.8366 23.7238 20.9281 24.1813 21.2941C24.4559 21.5138 24.8036 21.6785 24.9866 21.6785C25.1696 21.6785 25.5173 21.5138 25.7918 21.2941C26.2494 20.9281 26.2677 20.8549 26.2677 19.0431C26.2677 17.4875 26.2128 17.1032 25.9565 16.8836C25.499 16.4809 24.9683 16.3711 24.4925 16.5541Z" fill="#EC7E4A" />
                                        <path d="M16.2201 19.0431C16.0005 19.3176 15.8358 19.6653 15.8358 19.83C15.8358 20.196 17.3914 22.9778 17.7208 23.1791C18.1234 23.4353 19.0751 23.3255 19.3679 22.9961C19.5509 22.8131 19.679 22.4105 19.679 22.0994C19.679 21.4954 18.4345 19.1895 17.9038 18.7868C17.3731 18.4025 16.6227 18.5123 16.2201 19.0431Z" fill="#EC7E4A" />
                                        <path d="M32.0873 18.75C31.6297 19.0246 30.2938 21.5501 30.2938 22.1724C30.2938 22.4835 30.4402 22.8495 30.6232 23.0142C30.9709 23.3436 31.8677 23.4168 32.252 23.1789C32.5814 22.9776 34.137 20.1958 34.137 19.8298C34.137 19.6651 33.9723 19.3174 33.7527 19.0429C33.3684 18.5487 32.6729 18.4206 32.0873 18.75Z" fill="#EC7E4A" />
                                        <path d="M10.6383 24.6248C10.1442 25.0274 10.0161 25.6497 10.3455 26.2536C10.6017 26.7295 13.1273 28.0837 13.7678 28.0837C14.0789 28.0837 14.4449 27.9373 14.6097 27.7543C14.9391 27.4066 15.0123 26.5098 14.7744 26.1255C14.5731 25.7961 11.7913 24.2405 11.4253 24.2405C11.2605 24.2405 10.9128 24.4052 10.6383 24.6248Z" fill="#EC7E4A" />
                                        <path d="M36.7906 25.064C36.0036 25.5215 35.2716 25.9973 35.1984 26.1254C34.9422 26.528 35.052 27.4797 35.3814 27.7725C35.9121 28.2667 36.6442 28.1386 38.09 27.3333C39.4992 26.5647 39.8103 26.2169 39.8103 25.4483C39.8103 24.9725 39.0599 24.2404 38.5475 24.2404C38.3828 24.2404 37.5958 24.6064 36.7906 25.064Z" fill="#EC7E4A" />
                                        <path d="M28.5553 28.0654C28.2625 28.2484 27.0363 29.3831 25.865 30.591C24.0349 32.4577 23.7055 32.8786 23.7055 33.3544C23.7055 34.1048 24.2728 34.6721 25.0232 34.6721C25.5173 34.6721 25.9199 34.3427 27.8599 32.4211C30.4586 29.8772 30.7881 29.3831 30.4769 28.6327C30.2939 28.1935 29.6351 27.7177 29.2325 27.7177C29.1592 27.7177 28.8664 27.8824 28.5553 28.0654Z" fill="#EC7E4A" />
                                        <path d="M8.42395 32.476C7.94812 33.0067 7.98472 33.9218 8.49715 34.3427C8.84487 34.6172 9.2475 34.6721 10.7116 34.6721C12.4502 34.6721 12.5234 34.6538 12.8894 34.1963C13.109 33.9218 13.2738 33.574 13.2738 33.391C13.2738 33.208 13.109 32.8603 12.8894 32.5858C12.5234 32.1283 12.4502 32.11 10.6384 32.11C8.99128 32.11 8.71676 32.1466 8.42395 32.476Z" fill="#EC7E4A" />
                                        <path d="M37.0834 32.5858C36.8637 32.8603 36.699 33.208 36.699 33.391C36.699 33.574 36.8637 33.9218 37.0834 34.1963C37.4494 34.6538 37.5226 34.6721 39.3344 34.6721C40.89 34.6721 41.2743 34.6172 41.5122 34.3427C42.0247 33.7937 42.0613 33.1531 41.6403 32.6041C41.256 32.1283 41.2011 32.11 39.3527 32.11C37.5226 32.11 37.4494 32.1283 37.0834 32.5858Z" fill="#EC7E4A" />
                                    </svg>
                                </div>
                                <div class="text-end">
                                    {{__('message.bmr')}}
                                    <h2>{{ optional($data->userProfile)->bmr ?? '-' }}</h2>
                                    {{ __('message.calories') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            <div class="row">
                <div class="col-6">
                    <div class="card">
                        <div class="card-body" style="height: 145px">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="rounded p-3">
                                    <svg width="50" height="50" viewBox="0 0 50 50" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path d="M24.2699 4.26862C22.5362 4.6413 20.9968 5.85654 20.2353 7.47687C19.8626 8.27083 19.7978 8.6111 19.7978 9.85875C19.7978 11.3008 19.8464 11.4953 20.6566 13.0508C20.689 13.1318 19.6196 13.1804 18.2909 13.1804C16.9622 13.1804 15.4877 13.2614 15.0016 13.3424C12.4253 13.8285 10.1245 15.8053 9.29809 18.2358C9.16847 18.6571 8.29349 23.2426 7.38611 28.4277C5.78198 37.5339 5.73337 37.9066 5.89541 39.0408C6.3653 42.2977 8.90921 44.9226 12.1013 45.4249C13.5433 45.6518 37.2649 45.6356 38.6746 45.4087C41.8991 44.9064 44.4106 42.3301 44.8805 39.0408C45.0425 37.9066 44.9939 37.5339 43.3898 28.4277C42.4824 23.2426 41.6074 18.6571 41.4778 18.2358C40.6514 15.8053 38.3505 13.8285 35.7742 13.3424C35.2881 13.2614 33.8136 13.1804 32.485 13.1804C31.1563 13.1804 30.0869 13.1318 30.1193 13.0508C31.3831 10.6203 31.3507 8.59489 30.0707 6.6343C28.8716 4.83574 26.4087 3.83113 24.2699 4.26862ZM26.7814 6.84494C29.3739 8.02778 29.2767 11.8193 26.6518 12.9374C25.1125 13.5693 23.2653 12.9212 22.5038 11.4791C21.3209 9.19441 22.8602 6.53708 25.3879 6.53708C25.793 6.53708 26.4087 6.68291 26.7814 6.84494ZM35.9525 15.7729C37.3459 16.1618 38.9501 17.7659 39.3065 19.1432C39.4038 19.4835 40.1653 23.6801 41.0241 28.4925C42.7416 38.1172 42.8064 38.8302 42.1097 40.2723C41.6236 41.2445 40.6514 42.2167 39.6792 42.7028L38.9177 43.0754H25.3879H11.8582L11.0967 42.7028C10.1245 42.2167 9.15226 41.2445 8.66616 40.2723C7.96942 38.8302 8.03424 38.1172 9.75178 28.4925C10.6106 23.6801 11.3721 19.4835 11.4693 19.1432C11.8258 17.8145 13.4299 16.178 14.7586 15.7891C15.4715 15.5785 35.1909 15.5623 35.9525 15.7729Z" fill="#EC7E4A" />
                                        <path d="M17.1892 24.6039L16.7841 25.009L16.8327 29.4811L16.8813 33.9532L17.2702 34.261C17.7563 34.6499 18.2748 34.6499 18.7609 34.261C19.1174 33.9694 19.1498 33.8073 19.1984 32.2842L19.2632 30.6153L21.1428 32.5921C23.233 34.7795 23.5733 34.9416 24.3348 34.1638C25.0802 33.4347 24.9182 33.0782 23.0062 31.15L21.2724 29.3838L22.909 27.7149C23.8649 26.7589 24.5941 25.8677 24.6751 25.5923C24.8371 24.9603 24.3186 24.296 23.5895 24.2312C23.0872 24.1826 22.9252 24.296 21.1428 26.1432L19.2308 28.12V26.5645C19.2308 25.1062 19.1984 24.9603 18.8257 24.6039C18.3234 24.0854 17.7077 24.0854 17.1892 24.6039Z" fill="#EC7E4A" />
                                        <path d="M28.7744 24.4257C27.6726 24.8146 26.8462 25.6571 26.4411 26.8076C26.1333 27.6501 26.1171 30.9232 26.3925 31.8792C26.6518 32.7704 27.9967 34.1152 28.8878 34.3745C30.3785 34.812 31.9503 34.3583 32.9549 33.2241C33.927 32.1222 34.2997 29.8376 33.6354 28.995C33.3275 28.5899 33.2303 28.5737 31.8854 28.5737C30.6054 28.5737 30.4271 28.6061 30.1193 28.9464C29.3739 29.7241 29.8924 30.8422 30.9781 30.8422C31.6586 30.8422 31.7072 31.1014 31.1401 31.7171C30.8484 32.025 30.5892 32.1384 30.1355 32.1384C28.8878 32.1384 28.4666 31.3931 28.4666 29.2218C28.4666 27.8122 28.7906 26.9858 29.4874 26.6617C30.0707 26.3863 30.5244 26.4997 31.3021 27.1154C32.0313 27.6825 32.3229 27.7149 32.9062 27.2613C33.5868 26.7265 33.4896 26.0136 32.6146 25.2034C31.5614 24.2312 30.1193 23.9396 28.7744 24.4257Z" fill="#EC7E4A" />
                                    </svg>
                                </div>
                                <div class="text-end">
                                    {{__('message.ideal_weight')}}
                                    <h2>{{ optional($data->userProfile)->ideal_weight ?? '-' }}</h2>
                                    {{ optional($data->userProfile)->ideal_weight != null ? optional($data->userProfile)->weight_unit : ''}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">{{__('message.assigndiet')}}</h4>
                    </div>
                    <div class="text-center ms-3 ms-lg-0 ms-md-0">
                        <a href="javascript:void(0);" class="float-end btn btn-sm btn-primary" data-modal-form="form" data-size="small"
                            data--href="{{ route('add.assigndiet', $data['id']) }}"
                            data-app-title="{{ __('message.add_form_title',['form' => __('message.assigndiet')]) }}"
                            data-placement="top">{{ __('message.add_form_title', ['form' => __('message.diet')] ) }}</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive mt-4 assign-profile-max-height">
                        <table id="basic-table" class="table table-striped mb-0" role="grid">
                            <thead>
                                <tr>
                                    <th>{{ __('message.image') }}</th>
                                    <th>{{ __('message.title') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="diet-data">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">{{__('message.assignworkout')}}</h4>
                    </div>
                    <div class="text-center ms-3 ms-lg-0 ms-md-0">
                        <a href="javascript:void(0);" class="float-end btn btn-sm btn-primary" data-modal-form="form" data-size="small"
                            data--href="{{ route('add.assignworkout', $data['id']) }}"
                            data-app-title="{{ __('message.add_form_title',['form' => __('message.assignworkout')]) }}"
                            data-placement="top">{{ __('message.add_form_title', ['form' => __('message.workout')] ) }}</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive mt-4 assign-profile-max-height">
                        <table id="basic-table" class="table table-striped mb-0" role="grid">
                            <thead>
                                <tr>
                                    <th>{{ __('message.image') }}</th>
                                    <th>{{ __('message.title') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="workout-data">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@if ($type == 'subscription')
    <div class="row">
        <div class="col-lg-12">
            <div class="card card-block">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title mb-0">{{ __('message.list_form_title', [ 'form' => __('message.subscription_history') ]) }}</h4>
                    </div>
                </div>
                <div class="card-body">
                    {{ $dataTable->table(['class' => 'table  w-100'],false) }}
                </div>
            </div>
        </div>
    </div>
@endif

@if ($type == 'matrix')
    <div class="col-md-12">
         <div class="card">
            <div class="card-header d-flex justify-content-between flex-wrap">
                <div class="header-title">
                    <h4 class="card-title">{{__('message.weight_analysis')}}</h4>
                </div>
                <div class="d-flex align-items-center  ml-10">
                    <div class="d-flex align-items-center text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" viewBox="0 0 24 24" fill="currentColor">
                            <g id="Solid dot2">
                                <circle id="Ellipse 65" cx="12" cy="12" r="8" fill="currentColor"></circle>
                            </g>
                        </svg>
                        <div class="ms-1">
                            <h5 class="text-gray">{{__('message.weight')}}</h5>
                        </div>
                    </div>
                </div>
                <div class="dropdown  border-0">
                    <select name="weight-overview" id="weight-overview" class="form-control">
                        <option value="week">{{ __('message.this_week') }}</option>
                        <option value="month">  {{ __('message.this_month') }}</option>
                        <option value="year">{{ __('message.this_year') }}</option>
                        <option value="every">{{ __('message.all_data') }}</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="chart">
                    <div id="apex-line-area-weight"></div>
                </div>
            </div>
         </div>
    </div>

    <div class="col-md-12">
         <div class="card">
            <div class="card-header d-flex justify-content-between flex-wrap">
                <div class="header-title">
                    <h4 class="card-title">{{__('message.heart_rate_analysis')}}</h4>
                </div>
                <div class="d-flex align-items-center  ml-10">
                    <div class="d-flex align-items-center text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" viewBox="0 0 24 24" fill="currentColor">
                            <g id="Solid dot2">
                                <circle id="Ellipse 65" cx="12" cy="12" r="8" fill="currentColor"></circle>
                            </g>
                        </svg>
                        <div class="ms-1">
                            <h5 class="text-gray">{{__('message.heart_rate')}}</h5>
                        </div>
                    </div>
                </div>
                <div class="dropdown  border-0">
                    <select name="heart-rate-overview" id="heart-rate-overview" class="form-control">
                        <option value="week">{{ __('message.this_week') }}</option>
                        <option value="month">  {{ __('message.this_month') }}</option>
                        <option value="year">{{ __('message.this_year') }}</option>
                        <option value="every">{{ __('message.all_data') }}</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="chart">
                    <div id="apex-line-area-heart"></div>
                </div>
            </div>
         </div>
    </div>

    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between flex-wrap">
                <div class="header-title">
                    <h4 class="card-title">{{__('message.push_up_min')}}</h4>
                </div>
                <div class="d-flex align-items-center  ml-10">
                    <div class="d-flex align-items-center text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" viewBox="0 0 24 24" fill="currentColor">
                            <g id="Solid dot2">
                                <circle id="Ellipse 65" cx="12" cy="12" r="8" fill="currentColor"></circle>
                            </g>
                        </svg>
                        <div class="ms-1">
                            <h5 class="text-gray">{{__('message.push_up')}}</h5>
                        </div>
                    </div>
                </div>
                <div class="dropdown  border-0">
                    <select name="push-up-overview" id="push-up-overview" class="form-control">
                        <option value="week">{{ __('message.this_week') }}</option>
                        <option value="month">  {{ __('message.this_month') }}</option>
                        <option value="year">{{ __('message.this_year') }}</option>
                        <option value="every">{{ __('message.all_data') }}</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="chart">
                    <div id="apex-line-area-push-ups"></div>
                </div>
            </div>
        </div>
    </div>
@endif
@if($type == 'daily-step-water-tracking')
<div class="row">
   {{-- STEP COUNT --}}
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 progress-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <p class="mb-1 text-muted">{{ __('message.today_goal') }}</p>
                    <h4 class="fw-bold text-primary">
                        {{ $tracking['step_goal'] ?? 0 }}
                    </h4>
                </div>

                <div class="progress-wrapper">
                    <svg width="220" height="220" style="--progress-circle-offset: {{ data_get($tracking, 'step_circle.offset') }};">
                        <circle cx="110" cy="110" r="95" stroke="#ebc2c2ff" stroke-width="15" fill="none"/>
                        @if (data_get($tracking, 'step_circle.circumference'))
                        <circle id="progressCircle" class="progress-circle" cx="110" cy="110" r="95" stroke="#ff7b2e" stroke-width="15" fill="none" stroke-linecap="round" stroke-dasharray="{{ data_get($tracking, 'step_circle.circumference') }}" stroke-dashoffset="{{ data_get($tracking, 'step_circle.offset') }}" transform="rotate(-90 110 110)" />
                        @endif
                    </svg>

                    <div class="progress-value">
                        <svg xmlns="http://www.w3.org/2000/svg" height="40px" viewBox="0 -960 960 960" width="40px" fill="currentColor">
                            <path d="m278.67-40 114.66-578.67-91.33 38v134h-67.33v-178L430-707.33q14-6 29.5-7.34 15.5-1.33 30.17 2.34 16 3.66 28.5 12 12.5 8.33 20.5 21L580-614q29.33 46 75.17 73.33 45.83 27.34 100.16 27.34v66.66q-70-1.33-124-30-54-28.66-95.66-83L502-417.33l89.33 82V-40h-66.66v-246.67l-100-86.66L348-40h-69.33ZM540-749.33q-31 0-53.17-22.17-22.16-22.17-22.16-53.17t22.16-53.16Q509-900 540-900t53.17 22.17q22.16 22.16 22.16 53.16 0 31-22.16 53.17Q571-749.33 540-749.33Z"/>
                        </svg>

                        <div class="steps-number" id="stepValue">{{ $tracking['today_steps'] ?? 0 }}</div>
                        <div class="steps-text">{{ __('message.steps') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- WATER INTAKE --}}
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 progress-card">
            <div class="card-body d-flex align-items-center justify-content-between">

                <div>
                    <p class="mb-1 text-muted">{{ __('message.today_goal') }}</p>
                    <h4 class="fw-bold text-primary">
                        {{ $tracking['water_goal'] ?? 0 }}
                    </h4>
                </div>

                <div class="progress-wrapper">
                    <svg width="220" height="220"  style="--progress-circle-offset: {{ data_get($tracking, 'water_circle.offset') }};">

                        <circle cx="110" cy="110" r="95" stroke="#ebc2c2ff" stroke-width="15" fill="none"/>
                        @if (data_get($tracking, 'water_circle.circumference'))
                            <circle id="progressCircle" class="progress-circle" cx="110" cy="110" r="95" stroke="#ff7b2e" stroke-width="15" fill="none" stroke-linecap="round" stroke-dasharray="{{ data_get($tracking, 'water_circle.circumference') }}" stroke-dashoffset="{{ data_get($tracking, 'water_circle.offset') }}" transform="rotate(-90 110 110)" />
                        @endif
                    </svg>

                    <div class="progress-value">
                        <svg xmlns="http://www.w3.org/2000/svg" height="40px" viewBox="0 -960 960 960" width="40px" fill="currentColor">
                            <path d="M491-200q12-1 20.5-9.5T520-230q0-14-9-22.5t-23-7.5q-41 3-87-22.5T343-375q-2-11-10.5-18t-19.5-7q-14 0-23 10.5t-6 24.5q17 91 80 130t127 35ZM480-80q-137 0-228.5-94T160-408q0-100 79.5-217.5T480-880q161 137 240.5 254.5T800-408q0 140-91.5 234T480-80Zm0-80q104 0 172-70.5T720-408q0-73-60.5-165T480-774Q361-665 300.5-573T240-408q0 107 68 177.5T480-160Zm0-320Z"/>
                        </svg>

                        <div class="steps-number" id="stepValue">{{ $tracking['today_water'] ?? 0 }}</div>
                        <div class="steps-text">{{ __('message.glass') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between flex-wrap">
                <div class="header-title">
                </div>
                <div class="d-flex align-items-center  ml-10">
                    <div class="d-flex align-items-center text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" viewBox="0 0 24 24" fill="currentColor">
                            <g id="Solid dot2">
                                <circle id="Ellipse 65" cx="12" cy="12" r="8" fill="currentColor"></circle>
                            </g>
                        </svg>
                        <div class="ms-1">
                            <h5 class="text-gray">{{__('message.steps')}}</h5>
                        </div>
                    </div>
                </div>
                <div class="dropdown  border-0">
                    <select name="step-overview" id="step-overview" class="form-control">
                        <option value="week">{{ __('message.this_week') }}</option>
                        <option value="month">  {{ __('message.this_month') }}</option>
                        <option value="year">{{ __('message.this_year') }}</option>
                        <option value="every">{{ __('message.all_data') }}</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="chart">
                    <div id="apex-line-area-step-goal"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between flex-wrap">
                <div class="header-title">
                </div>
                <div class="d-flex align-items-center  ml-10">
                    <div class="d-flex align-items-center text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" viewBox="0 0 24 24" fill="currentColor">
                            <g id="Solid dot2">
                                <circle id="Ellipse 65" cx="12" cy="12" r="8" fill="currentColor"></circle>
                            </g>
                        </svg>
                        <div class="ms-1">
                            <h5 class="text-gray">{{__('message.water')}}</h5>
                        </div>
                    </div>
                </div>
                <div class="dropdown  border-0">
                    <select name="water-overview" id="water-overview" class="form-control">
                        <option value="week">{{ __('message.this_week') }}</option>
                        <option value="month">  {{ __('message.this_month') }}</option>
                        <option value="year">{{ __('message.this_year') }}</option>
                        <option value="every">{{ __('message.all_data') }}</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="chart">
                    <div id="apex-line-area-water-goal"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

</div>
</div>
</div>
</div>
</x-app-layout>
