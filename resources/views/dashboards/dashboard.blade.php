@push('scripts')

@if( $data['subscription_setting'] == 1 && $auth_user->can('subscription-list') )
<script>
let currencyPosition = @json($data['currency_position'] ?? 'left');
let currencySymbol = @json($data['currency_symbol'] ?? '$');

function renderSubscriptionAmount(amount) {
	const formattedAmount = Number(amount || 0).toFixed(2);

	return currencyPosition === 'left' ? `${currencySymbol}${formattedAmount}` : `${formattedAmount}${currencySymbol}`;
}

function getSubscriptionSeries() {
	return [
		{
			name: "{{ __('message.plans') }}",
			data: [],
			customType: 'plans'
		},
		{
			name: "{{ __('message.amount') }}",
			data: [],
			customType: 'amount'
		}
	];
}

function mapSubscriptionSeries(chartData) {
	const series = getSubscriptionSeries();

	series[0].data = chartData.plan_counts ?? [];
	series[1].data = chartData.amounts ?? [];

	return series;
}

$(document).ready(function () {

	const lineChartElement = document.querySelector('#apex-line-subscription');
	const pieChartElement = document.querySelector('#apex-pie-subscription');

	if (!lineChartElement || !pieChartElement) {
		return;
	}

	let defaultLine = @json($data['line_chart']);
	let defaultPie  = @json($data['pie_chart']);

	// LINE CHART
	let subscriptionChart = new ApexCharts(lineChartElement, {
		chart: {
			height: 340,
			type: 'line',
			toolbar: { show: false },
			zoom: { enabled: false }
		},
		stroke: {
			curve: 'straight',
			width: [0,3]
		},
		xaxis: {
			categories: defaultLine.labels
		},
		yaxis: [
			{
				title: {
					text: "{{ __('message.amount') }}",
					style: {
						color: "#F16A1B"
					},
				},
				labels: {
					formatter: function (val) {
						return renderSubscriptionAmount(val);
					}
				}
			}
		],
		series: [
			{ name: "{{ __('message.plans') }}", data: defaultLine.plan_counts, customType: 'plans' },
			{ name: "{{ __('message.amount') }}", data: defaultLine.amounts, customType: 'amount' }
		],
		tooltip: {
			y: {
				formatter: function (val, { seriesIndex, w }) {
					const seriesType = w.config.series[seriesIndex].customType;
					if (seriesType == "amount") {
						return renderSubscriptionAmount(val);
					}			
					return Number(val || 0).toFixed(0);
				}
			}
		},
		colors: ['#F16A1B', '#FF9A5A']
	});

	subscriptionChart.render();
	
	// PIE CHART
	let pieChart = new ApexCharts(pieChartElement, {
		chart: { type: 'pie', height: 350 },
		labels: defaultPie.package_names,
		series: defaultPie.package_percentages,
		dataLabels: {
			enabled: true,
			formatter: function (val) {
				return `${Number(val || 0).toFixed(0)}%`;
			}
		},
		
		tooltip: {
			y: {
				formatter: function (val) {
					return Number(val || 0).toFixed(0);
				}
			}
		},
		noData: {
			text: "{{ __('message.no_data_found') }}",
			align: 'center',
			verticalAlign: 'middle',
			style: {
				color: '#F16A1B',
				fontSize: '14px'
			}
		}
	});

	pieChart.render();
	
	// FILTER - LINE	
	$(document).on('change','#subscription-overview', function() {
		let val = $(this).val();
		ajaxSubscriptionChart(subscriptionChart, null, val, 'line');
	});

	// FILTER - PIE
	$(document).on('change','#pie-filter', function() {
		let val = $(this).val();
		ajaxSubscriptionChart(null, pieChart, val, 'pie');
	});

});

function ajaxSubscriptionChart(lineChart, pieChart, filter = 'week', type = 'line')
{
	$.ajax({
		type: 'GET',
		url: "{{ route('dashboard') }}",
		data: { filter: filter, type: type },
		success: function (res) {

			// LINE UPDATE
			if (lineChart && type === 'line') {
				currencyPosition = res.currency_position ?? currencyPosition;
				currencySymbol = res.currency_symbol ?? currencySymbol;

				lineChart.updateOptions({
					xaxis: { categories: res.labels },
					series: mapSubscriptionSeries(res)
				});
			}

			// PIE UPDATE
			if (pieChart && type === 'pie') {
				pieChart.updateOptions({
					labels: res.package_names,
					series: res.package_percentages
				});
			}
		}
	});
}
</script>
@endif
@endpush
<x-app-layout :assets="$assets ?? []">
   	<div class="row">
      	<div class="col-md-12 col-lg-12 pt-1">
         	<div class="row ">
				<div class="col-lg-3 col-md-6" >
					<div class=" card card-slide" data-aos="fade-up" data-aos-delay="700">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<circle cx="9" cy="6" r="4" stroke="#fff" stroke-width="1.5"/>
										<path d="M15 9C16.6569 9 18 7.65685 18 6C18 4.34315 16.6569 3 15 3" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
										<ellipse cx="9" cy="17" rx="7" ry="4" stroke="#fff" stroke-width="1.5"/>
										<path d="M18 14C19.7542 14.3847 21 15.3589 21 16.5C21 17.5293 19.9863 18.4229 18.5 18.8704" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
									</svg>
								</div>

								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.user') }}</p>
									<h5 class="counter">{{ $data['dashboard']['total_user'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class="card card-slide" data-aos="fade-up" data-aos-delay="700">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M8.32031 12.1982L12.2003 8.31823M15.3043 11.4222L11.4243 15.3023" stroke="white" stroke-width="1.5"/>
										<path d="M3.43157 15.6193C2.52737 14.7151 2.07528 14.263 2.0108 13.7109C1.9964 13.5877 1.9964 13.4632 2.0108 13.3399C2.07528 12.7879 2.52737 12.3358 3.43156 11.4316C4.33575 10.5274 4.78785 10.0753 5.33994 10.0108C5.46318 9.9964 5.58768 9.9964 5.71092 10.0108C6.26301 10.0753 6.71511 10.5274 7.6193 11.4316L12.5684 16.3807C13.4726 17.2849 13.9247 17.737 13.9892 18.2891C14.0036 18.4123 14.0036 18.5368 13.9892 18.6601C13.9247 19.2122 13.4726 19.6642 12.5684 20.5684C11.6642 21.4726 11.2122 21.9247 10.6601 21.9892C10.5368 22.0036 10.4123 22.0036 10.2891 21.9892C9.73699 21.9247 9.28489 21.4726 8.3807 20.5684L3.43157 15.6193Z" stroke="white" stroke-width="1.5"/>
										<path d="M11.4316 7.6193C10.5274 6.71511 10.0753 6.26301 10.0108 5.71092C9.9964 5.58768 9.9964 5.46318 10.0108 5.33994C10.0753 4.78785 10.5274 4.33576 11.4316 3.43156C12.3358 2.52737 12.7879 2.07528 13.3399 2.0108C13.4632 1.9964 13.5877 1.9964 13.7109 2.0108C14.263 2.07528 14.7151 2.52737 15.6193 3.43156L20.5684 8.3807C21.4726 9.28489 21.9247 9.73699 21.9892 10.2891C22.0036 10.4123 22.0036 10.5368 21.9892 10.6601C21.9247 11.2122 21.4726 11.6642 20.5684 12.5684C19.6642 13.4726 19.2122 13.9247 18.6601 13.9892C18.5368 14.0036 18.4123 14.0036 18.2891 13.9892C17.737 13.9247 17.2849 13.4726 16.3807 12.5684L11.4316 7.6193Z" stroke="white" stroke-width="1.5"/>
										<path d="M18.0195 2.49805L21.1235 5.60206" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M2.49609 18.0181L5.6001 21.1221" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.equipment') }}</p>
									<h5 class="counter" >{{ $data['dashboard']['total_equipment'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class=" card card-slide" data-aos="fade-up" data-aos-delay="800">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M3 22H21" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M3 11C3 10.0572 3 9.58579 3.29289 9.29289C3.58579 9 4.05719 9 5 9C5.94281 9 6.41421 9 6.70711 9.29289C7 9.58579 7 10.0572 7 11V17C7 17.9428 7 18.4142 6.70711 18.7071C6.41421 19 5.94281 19 5 19C4.05719 19 3.58579 19 3.29289 18.7071C3 18.4142 3 17.9428 3 17V11Z" stroke="white" stroke-width="1.5"/>
										<path d="M10 7C10 6.05719 10 5.58579 10.2929 5.29289C10.5858 5 11.0572 5 12 5C12.9428 5 13.4142 5 13.7071 5.29289C14 5.58579 14 6.05719 14 7V17C14 17.9428 14 18.4142 13.7071 18.7071C13.4142 19 12.9428 19 12 19C11.0572 19 10.5858 19 10.2929 18.7071C10 18.4142 10 17.9428 10 17V7Z" stroke="white" stroke-width="1.5"/>
										<path d="M17 4C17 3.05719 17 2.58579 17.2929 2.29289C17.5858 2 18.0572 2 19 2C19.9428 2 20.4142 2 20.7071 2.29289C21 2.58579 21 3.05719 21 4V17C21 17.9428 21 18.4142 20.7071 18.7071C20.4142 19 19.9428 19 19 19C18.0572 19 17.5858 19 17.2929 18.7071C17 18.4142 17 17.9428 17 17V4Z" stroke="white" stroke-width="1.5"/>
									</svg>
								</div>								
								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.level') }}</p>
									<h5 class="counter" >{{ $data['dashboard']['total_level'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class=" card card-slide" data-aos="fade-up" data-aos-delay="800">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M6.38892 13.1614C8.22254 12.0779 12.9999 11.0891 16.6405 14.8096" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
										<path d="M2 10.5588C5.1153 10.2051 6.39428 10.6706 8.75105 12.2516M15.5021 13.7518L13.8893 6.66258C13.8186 6.35178 13.4791 6.18556 13.1902 6.32034L10.3819 7.6308C10.1384 7.74442 9.85788 7.75857 9.61117 7.65213C8.87435 7.33425 8.38405 6.97152 7.86685 6.31394C7.61986 5.99992 7.54201 5.5868 7.62818 5.19668C7.87265 4.0899 8.12814 3.34462 8.62323 2.31821C8.70119 2.15659 8.86221 2.05093 9.04141 2.04171C11.0466 1.93856 12.3251 2.01028 14.2625 2.44371C14.5804 2.51485 14.8662 2.69558 15.0722 2.948C19.8635 8.8193 21.3943 11.9968 21.9534 16.6216C21.9872 16.9004 21.8964 17.1818 21.7073 17.3895C17.6861 21.8064 14.7759 22.3704 8.75105 20.0604C6.65624 21.5587 5.07425 21.8624 2.25004 21.3106" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
									</svg>
								</div>								
								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.bodypart') }}</p>
									<h5 class="counter" >{{ $data['dashboard']['total_bodypart'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class=" card card-slide" data-aos="fade-up" data-aos-delay="900">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M6.60935 6.09382C6.33646 6.21521 6.11215 6.47254 5.98131 6.80756C5.8729 7.08432 5.86916 7.11831 5.85047 7.96799L5.83178 8.84193L5.14019 8.86626C4.51215 8.89048 4.43365 8.90027 4.28786 9.00222C4.04113 9.17209 3.85047 9.42464 3.75328 9.70626C3.67103 9.93932 3.66356 10.0315 3.65234 10.9395L3.64113 11.9252H2.91215C2.18692 11.9252 2.18318 11.9252 2.09346 12.0465L2 12.1631V14.0323V15.9017L2.0972 15.9988C2.19066 16.0959 2.24673 16.1007 2.91963 16.1007H3.64113L3.65234 17.1058C3.66356 18.0914 3.6673 18.1206 3.76449 18.3585C3.81683 18.4945 3.94019 18.6983 4.02991 18.8051C4.31403 19.145 4.39626 19.1742 5.1701 19.1985L5.85047 19.2179V20.053C5.85047 20.8929 5.85047 20.8929 5.96262 21.1892C6.08599 21.5047 6.24673 21.7184 6.4972 21.8884C6.643 21.9903 6.70281 22 7.28973 22C7.90655 22 7.92898 21.9952 8.11586 21.8689C8.34396 21.7135 8.51967 21.4659 8.63928 21.1308L8.72896 20.8832V18.4896V16.1007H11.9813H15.2336V18.2711C15.2336 19.4703 15.2486 20.5725 15.271 20.723C15.3346 21.2377 15.5327 21.6116 15.8729 21.8543C16.0748 22 16.0748 22 16.7103 22C17.2972 22 17.3571 21.9903 17.5029 21.8884C17.7532 21.7184 17.914 21.5047 18.0374 21.1892C18.1495 20.8929 18.1495 20.8929 18.1495 20.053V19.2179L18.8336 19.1985C19.4841 19.1839 19.5215 19.1742 19.7009 19.0527C19.9402 18.8877 20.1121 18.6692 20.2356 18.3585C20.3327 18.1206 20.3364 18.0914 20.3477 17.1058L20.3589 16.1007H21.0804C21.7533 16.1007 21.8094 16.0959 21.9029 15.9988L22 15.9017V14.0372C22 12.2795 21.9963 12.1728 21.929 12.066C21.8579 11.9494 21.843 11.9494 21.1103 11.9348L20.3589 11.9202L20.3477 10.9395C20.3364 10.0655 20.329 9.93437 20.2579 9.73532C20.1458 9.42949 19.9626 9.17209 19.7421 9.0216C19.5589 8.89532 19.5215 8.89048 18.8598 8.86626L18.1682 8.84193L18.1495 7.96799C18.1309 7.16686 18.1234 7.07461 18.0411 6.85611C17.9252 6.55023 17.7047 6.26376 17.4804 6.13266C17.2448 5.99186 16.3925 5.95302 16.0823 6.06955C15.7757 6.18607 15.529 6.46283 15.3757 6.86097L15.2524 7.19113L15.2411 9.55566L15.2299 11.9252H11.9813H8.73269L8.7215 9.48288L8.71031 7.04547L8.59816 6.78328C8.45983 6.46283 8.19443 6.17636 7.93646 6.0744C7.65608 5.96758 6.8673 5.97729 6.60935 6.09382ZM7.90655 6.81242C7.98131 6.87068 8.07848 7.00663 8.1234 7.11345L8.20562 7.30281L8.19816 14.076C8.18689 20.8347 8.18689 20.8542 8.1084 20.9852C7.92898 21.296 7.87665 21.3202 7.28973 21.3202C6.68412 21.3202 6.61683 21.2911 6.45982 20.9269L6.37384 20.7376V14.042C6.37384 7.3902 6.37384 7.34165 6.44861 7.13773C6.59066 6.76386 6.75888 6.68132 7.34581 6.69588C7.71589 6.70559 7.7944 6.72017 7.90655 6.81242ZM17.3346 6.81727C17.4131 6.88039 17.5029 7.01148 17.5477 7.13773C17.6262 7.34165 17.6262 7.36593 17.6262 14.0178V20.6939L17.544 20.9076C17.3906 21.2863 17.3271 21.3202 16.7103 21.3202C16.1234 21.3202 16.071 21.296 15.8916 20.9852C15.8131 20.8542 15.8131 20.8347 15.8019 14.076L15.7944 7.30281L15.8767 7.11345C16.0299 6.75415 16.1869 6.68132 16.7663 6.69588C17.1402 6.70559 17.215 6.72017 17.3346 6.81727ZM5.84299 14.0226L5.83178 18.5041L5.31589 18.5187C4.74767 18.5332 4.56449 18.4896 4.39253 18.3051C4.15702 18.0429 4.16823 18.2759 4.16823 14.0372C4.16823 9.70131 4.15328 9.9587 4.44113 9.68677L4.59066 9.54597H5.22244H5.85047L5.84299 14.0226ZM19.4953 9.61884C19.5552 9.6577 19.6524 9.77419 19.7084 9.87129L19.8131 10.0461L19.8243 13.911C19.8318 16.5328 19.8243 17.8341 19.7944 17.9554C19.7458 18.174 19.544 18.4119 19.3495 18.4798C19.2636 18.509 18.9683 18.5235 18.6841 18.5187L18.1682 18.5041L18.1571 14.0226L18.1495 9.54597H18.7663C19.2448 9.54597 19.4131 9.5605 19.4953 9.61884ZM3.63739 14.0226L3.62618 15.3967H3.08412H2.54206L2.53084 14.0226L2.52337 12.6534H3.08412H3.64487L3.63739 14.0226ZM15.2336 14.0372V15.421L11.9925 15.4112L8.74769 15.3967L8.73642 14.0226L8.72896 12.6534H11.9813H15.2336V14.0372ZM21.4692 14.0226L21.4579 15.3967H20.9159H20.3739L20.3626 14.0226L20.3552 12.6534H20.9159H21.4767L21.4692 14.0226Z" fill="white" stroke="white" stroke-width="0.4"/>
									</svg>
								</div>								
								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.workouttype') }}</p>
									<h5 class="counter" >{{ $data['dashboard']['total_workouttype'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class=" card card-slide" data-aos="fade-up" data-aos-delay="850">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M14.1009 2.64567C14.1009 3.69258 13.2522 4.54134 12.2051 4.54134C11.1581 4.54134 10.3093 3.69258 10.3093 2.64567C10.3093 1.59876 11.1581 0.75 12.2051 0.75C13.2522 0.75 14.1009 1.59876 14.1009 2.64567Z" stroke="white" stroke-width="1.5"/>
										<path d="M1.99902 8.50402V7.00612C1.99902 6.58326 2.3459 6.24263 2.76869 6.25032L12.3933 6.4253L22.2071 6.25007C22.6298 6.24252 22.9765 6.58311 22.9765 7.00587V8.50402C22.9765 8.92151 22.6381 9.25995 22.2206 9.25995H16.4162C15.9408 9.25995 15.5834 9.69361 15.6742 10.1603L17.9978 22.0998C18.0886 22.5664 17.7312 23.0001 17.2558 23.0001H16.2843C15.9478 23.0001 15.6518 22.7777 15.5582 22.4544L13.6852 15.9867C13.5916 15.6635 13.2956 15.4411 12.9591 15.4411H11.8413C11.4983 15.4411 11.1983 15.672 11.1105 16.0036L9.40821 22.4375C9.32047 22.7691 9.02045 23.0001 8.67743 23.0001H7.70562C7.23489 23.0001 6.87864 22.5745 6.96149 22.1111L9.10041 10.1489C9.18326 9.68555 8.82701 9.25995 8.35628 9.25995H2.75495C2.33746 9.25995 1.99902 8.92151 1.99902 8.50402Z" stroke="white" stroke-width="1.5"/>
									</svg>
								</div>								
								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.exercise') }}</p>
									<h5 class="counter" >{{ $data['dashboard']['total_exercise'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class=" card card-slide" data-aos="fade-up" data-aos-delay="800">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<circle cx="15" cy="4" r="2" stroke="white" stroke-width="1.5"/>
										<path d="M11 16.0002V14.3667C11 13.8177 10.7561 13.297 10.3344 12.9455L9.33793 12.1152C8.61946 11.5164 8.57018 10.43 9.2315 9.76871L10.8855 8.11473C11.4193 7.5809 11.2452 6.67671 10.5513 6.37932C9.26627 5.82861 7.79304 5.94205 6.60752 6.68301L4.5 8.00021" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
										<path d="M7 14L6.67157 14.3284C6.09351 14.9065 5.80448 15.1955 5.43694 15.3478C5.0694 15.5 4.66065 15.5 3.84315 15.5H3" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
										<path d="M12.5 10H15.5" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
										<path d="M19.4888 22.0001H3.08684C2.48659 22.0001 2 21.5135 2 20.9133C2 20.3853 2.37943 19.9337 2.89949 19.8427L19.0559 17.0153C20.5926 16.7464 22 17.9289 22 19.4889C22 20.8758 20.8757 22.0001 19.4888 22.0001Z" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>
										<path d="M19.2916 8.88902L18.5499 8.77777L18.5499 8.77777L19.2916 8.88902ZM20.8773 7.22454L21.0244 7.95998L21.0244 7.95998L20.8773 7.22454ZM22.1471 7.73544C22.5533 7.6542 22.8167 7.25908 22.7354 6.85291C22.6542 6.44674 22.2591 6.18333 21.8529 6.26456L22.1471 7.73544ZM18.7417 17.6113L20.0333 9.00028L18.5499 8.77777L17.2583 17.3887L18.7417 17.6113ZM21.0244 7.95998L22.1471 7.73544L21.8529 6.26456L20.7302 6.48911L21.0244 7.95998ZM20.0333 9.00028C20.0862 8.64782 20.1178 8.44487 20.1568 8.2985C20.1744 8.23252 20.1885 8.19883 20.1965 8.18288C20.2002 8.17549 20.2024 8.17218 20.2029 8.17144C20.2034 8.17082 20.2034 8.17074 20.2037 8.17041L19.1177 7.13579C18.8906 7.37412 18.7782 7.64686 18.7076 7.91172C18.6418 8.15825 18.5978 8.45884 18.5499 8.77777L20.0333 9.00028ZM20.7302 6.48911C20.414 6.55235 20.1159 6.61086 19.8728 6.68852C19.6117 6.77197 19.3447 6.89746 19.1177 7.13579L20.2037 8.17041C20.2041 8.17009 20.2041 8.17 20.2047 8.16955C20.2054 8.169 20.2086 8.1666 20.2159 8.16256C20.2314 8.15385 20.2644 8.13813 20.3294 8.11734C20.4737 8.07123 20.6749 8.02987 21.0244 7.95998L20.7302 6.48911Z" fill="white"/>
								</svg>
								</div>								
								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.workout') }}</p>
									<h5 class="counter" >{{ $data['dashboard']['total_workout'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class=" card card-slide" data-aos="fade-up" data-aos-delay="800">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<g>
											<rect fill="none" height="24" width="24" />
											<path d="M8.16,11c-1.43,0.07-3.52,0.57-4.54,2h6.55L8.16,11z" enable-background="new" opacity=".3" />
											<path d="M1,21h15.01v0.98c0,0.56-0.45,1.01-1.01,1.01H2.01C1.45,22.99,1,22.54,1,21.98V21z M20.49,23.31L16,18.83V19H1v-2h13.17 l-2-2H1c0-3.24,2.46-5.17,5.38-5.79l-5.7-5.7L2.1,2.1L13,13l2,2l6.9,6.9L20.49,23.31z M10.17,13l-2-2c-1.42,0.06-3.52,0.56-4.55,2 H10.17z M23,5h-5V1h-2v4h-5l0.23,2h9.56l-1,9.97l1.83,1.83L23,5z" fill="white"/>
										</g>
									</svg>
								</div>								
								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.diet') }}</p>
									<h5 class="counter" >{{ $data['dashboard']['total_diet'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				@if($data['subscription_setting'] == 1 && $auth_user->can('subscription-list'))
				<div class="col-lg-3 col-md-6">
					<div class=" card card-slide" data-aos="fade-up" data-aos-delay="850">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M5 20.3884H7.25993C8.27079 20.3884 9.29253 20.4937 10.2763 20.6964C12.0166 21.0549 13.8488 21.0983 15.6069 20.8138C16.4738 20.6734 17.326 20.4589 18.0975 20.0865C18.7939 19.7504 19.6469 19.2766 20.2199 18.7459C20.7921 18.216 21.388 17.3487 21.8109 16.6707C22.1736 16.0894 21.9982 15.3762 21.4245 14.943C20.7873 14.4619 19.8417 14.462 19.2046 14.9433L17.3974 16.3084C16.697 16.8375 15.932 17.3245 15.0206 17.4699C14.911 17.4874 14.7962 17.5033 14.6764 17.5172M14.6764 17.5172C14.6403 17.5214 14.6038 17.5254 14.5668 17.5292M14.6764 17.5172C14.8222 17.486 14.9669 17.396 15.1028 17.2775C15.746 16.7161 15.7866 15.77 15.2285 15.1431C15.0991 14.9977 14.9475 14.8764 14.7791 14.7759C11.9817 13.1074 7.62942 14.3782 5 16.2429M14.6764 17.5172C14.6399 17.525 14.6033 17.5292 14.5668 17.5292M14.5668 17.5292C14.0434 17.5829 13.4312 17.5968 12.7518 17.5326" stroke="white" stroke-width="1.5" stroke-linecap="round"></path>
										<rect x="2" y="14" width="3" height="8" rx="1.5" stroke="white" stroke-width="1.5"></rect>
										<path d="M11.1992 9H14.7992" stroke="white" stroke-width="1.5" stroke-linecap="round"></path>
										<path d="M18.7654 6.78078L18.9029 5.56316C19.0109 4.60685 19.0649 4.1287 18.8686 3.93104C18.7624 3.82412 18.618 3.7586 18.4636 3.7473C18.1782 3.72641 17.8198 4.06645 17.1029 4.74654C16.7321 5.09825 16.5468 5.2741 16.34 5.30134C16.2254 5.31643 16.1086 5.30091 16.0028 5.25654C15.8119 5.17646 15.6846 4.95906 15.43 4.52426L14.0878 2.23243C13.6067 1.41081 13.3661 1 13 1C12.6339 1 12.3933 1.41081 11.9122 2.23243L10.57 4.52426C10.3154 4.95906 10.1881 5.17646 9.99716 5.25654C9.89135 5.30091 9.77461 5.31643 9.66002 5.30134C9.45323 5.2741 9.26786 5.09825 8.89712 4.74654C8.18025 4.06645 7.82181 3.72641 7.53639 3.7473C7.38199 3.7586 7.23759 3.82412 7.13139 3.93104C6.93508 4.1287 6.98908 4.60685 7.09708 5.56316L7.2346 6.78078C7.46119 8.78708 7.57449 9.79024 8.28406 10.3951C8.99363 11 10.0571 11 12.184 11H13.816C15.9429 11 17.0064 11 17.7159 10.3951C18.4255 9.79024 18.5388 8.78708 18.7654 6.78078Z" stroke="white" stroke-width="1.5"></path>
									</svg>
								</div>								
								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.no_of_subscription') }}</p>
									<h5 class="counter" >{{ $data['total_subscription'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-6">
					<div class=" card card-slide" data-aos="fade-up" data-aos-delay="850">
						<div class="card-body">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded p-3">
									<svg height="24" viewBox="0 -960 960 960" width="24" xmlns="http://www.w3.org/2000/svg" fill="white">
										<path d="M546.67-426.67q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35ZM240-293.33q-27.5 0-47.08-19.59-19.59-19.58-19.59-47.08v-373.33q0-27.5 19.59-47.09Q212.5-800 240-800h613.33q27.5 0 47.09 19.58Q920-760.83 920-733.33V-360q0 27.5-19.58 47.08-19.59 19.59-47.09 19.59H240ZM333.33-360H760q0-39 27.17-66.17 27.16-27.16 66.16-27.16V-640q-39 0-66.16-27.17Q760-694.33 760-733.33H333.33q0 39-27.16 66.16Q279-640 240-640v186.67q39 0 66.17 27.16Q333.33-399 333.33-360ZM800-160H106.67q-27.5 0-47.09-19.58Q40-199.17 40-226.67V-680h66.67v453.33H800V-160ZM240-360v-373.33V-360Z"/>
									</svg>
								</div>								
								<div class="text-right dashboard-show-data">
									<p class="mb-0">{{ __('message.subscription_revenue') }}</p>
									<h5 class="counter" data-toggle="tooltip" title="{{ $data['subscription_amount']  }}"> {{ $data['total_subscription_amount'] }}</h5>
								</div>
							</div>
						</div>
					</div>
				</div>
				@endif
			</div>
		</div>

		@if(($data['subscription_setting'] ?? 0) == 1 && $auth_user->can('subscription-list'))
			<div class="col-md-6">
				<div class="card" data-aos="fade-up" data-aos-delay="850">
					<div class="card-header d-flex justify-content-between flex-wrap graph-header">
						
						<div class="header-title">
							<h4 class="card-title">{{ __('message.subscription_overview')}}</h4>
						</div>

						<div class="dropdown d-flex align-items-center gap-2">
							<select id="subscription-overview" class="form-control">
								<option value="week">{{__('message.this_week')}}</option>
								<option value="month">{{__('message.this_month')}}</option>
								<option value="year">{{__('message.this_year')}}</option>
								<option value="all">{{__('message.all_data')}}</option>
							</select>
						</div>
					</div>

					<div class="card-body">
						<div id="apex-line-subscription"></div>
					</div>
				</div>
			</div>

			<div class="col-md-6">
				<div class="card" data-aos="fade-up" data-aos-delay="900">
					<div class="card-header d-flex justify-content-between flex-wrap graph-header">
						<div class="header-title">
							<h4 class="card-title">{{ __('message.package_overview')}}</h4>
						</div>
						<div class="dropdown d-flex align-items-center gap-2">
							<select id="pie-filter" class="form-control">
								<option value="week">{{__('message.this_week')}}</option>
								<option value="month">{{__('message.this_month')}}</option>
								<option value="year">{{__('message.this_year')}}</option>
								<option value="all">{{__('message.all_data')}}</option>
							</select>
						</div>
					</div>
					<div class="card-body">
						<div id="apex-pie-subscription"></div>
					</div>
				</div>
			</div>

			<div class="col-md-6">
				<div class="card" data-aos="fade-up" data-aos-delay="950">
					<div class="card-header d-flex justify-content-between flex-wrap">
						<div class="header-title">
							<h4 class="card-title">{{ __('message.list_form_title',['form' => __('message.recent_subscription')] ) }}</h4>
						</div>
						<div class="card-action">
							<a href="{{ route('subscription.index') }}" data-bs-toggle="tooltip" title="{{ __('message.list_form_title', [ 'form' => __('message.subscription') ]) }}">{{ __('message.see_all') }}</a>
						</div>
					</div>
					<div class="card-body p-0">
						<div class="table-responsive mt-4 dashboard_table_list">
							<table class="table table-striped mb-0" role="grid">
								<thead>
									<tr>
										<th>{{ __('message.date') }}</th>
										<th>{{ __('message.user') }}</th>
										<th>{{ __('message.package') }}</th>
										<th>{{ __('message.amount') }}</th>
									</tr>
								</thead>
								<tbody>
									@if( count($data['recent_subscription']) > 0 )
										@foreach( $data['recent_subscription'] as $recent_subscription )
											<tr>
												<td>{{ $recent_subscription->subscription_start_date }}</td>
												<td>{{ $recent_subscription?->user?->display_name }}</td>
												<td><strong>{{ $recent_subscription?->package?->name }}</strong> </td>
												<td>{{ $recent_subscription->formated_total_amount ?? 0 }}</td>
											</tr>
										@endforeach
									@else
										<tr>
											<td colspan="4">{{ __('message.not_found_entry', [ 'name' => __('message.recent_subscription') ]) }}</td>
										</tr>
									@endif
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="col-md-6">
				<div class="card" data-aos="fade-up" data-aos-delay="950">
					<div class="card-header d-flex justify-content-between flex-wrap">
						<div class="header-title">
							<h4 class="card-title">{{ __('message.list_form_title',['form' => __('message.expire_soon')] ) }}</h4>
						</div>
						<div class="card-action">
							<a href="{{ route('subscription.index', ['status' => 'expire_soon']) }}" data-bs-toggle="tooltip" title="{{ __('message.list_form_title', [ 'form' => __('message.expire_soon_subscription') ]) }}">{{ __('message.see_all') }}</a>
						</div>
					</div>
					
					<div class="card-body p-0">
						<div class="table-responsive mt-4 dashboard_table_list">
							<table class="table table-striped mb-0" role="grid">
								<thead>
									<tr>
										<th>{{ __('message.user') }}</th>
										<th>{{ __('message.subscription_start_date') }}</th>
										<th>{{ __('message.subscription_end_date') }}</th>
										<th>{{ __('message.package') }}</th>
										<th>{{ __('message.amount') }}</th>
									</tr>
								</thead>
								<tbody>
									@if( count($data['expire_soon_subscription']) > 0 )
										@foreach( $data['expire_soon_subscription'] as $expire_soon )
											<tr>
												<td> {{ $expire_soon?->user?->display_name }} </td>
												<td> {{ $expire_soon->subscription_start_date}} </td>
												<td> {{ $expire_soon->subscription_end_date}} </td>
												<td> <strong>{{ $expire_soon?->package?->name }}</strong> </td>
												<td> {{ $expire_soon->formated_total_amount ?? 0 }} </td>
											</tr>
										@endforeach
									@else
										<tr>
											<td colspan="4">{{ __('message.not_found_entry', [ 'name' => __('message.recent_subscription') ]) }}</td>
										</tr>
									@endif
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		@endif

      	<div class="col-md-12 col-lg-12">
         	<div class="row">
				@if($auth_user->can('exercise-list'))
					<div class="col-md-6">
						<div class="card" data-aos="fade-up" data-aos-delay="900">
							<div class="card-header d-flex justify-content-between flex-wrap">
								<div class="header-title">
									<h4 class="card-title">{{ __('message.list_form_title',['form' => __('message.exercise')] ) }}</h4>
								</div>
								<div class="card-action">
									<a href="{{ route('exercise.index') }}" data-bs-toggle="tooltip" title="{{ __('message.list_form_title', [ 'form' => __('message.exercise') ]) }}">{{ __('message.see_all') }}</a>
								</div>
							</div>
							<div class="card-body p-0">
								<div class="table-responsive mt-4 dashboard_table_list">
									<table id="basic-table" class="table table-striped mb-0 " role="grid">
										<thead>
											<tr>
												<th>{{ __('message.image') }}</th>
												<th>{{ __('message.title') }}</th>	
											</tr>
										</thead>
										<tbody>
											@if( count($data['exercise']) > 0 )
												@foreach ($data['exercise'] as $exercise)
												<tr>
													<td><img src="{{ getSingleMedia($exercise, 'exercise_image') }}" alt="exercise-image" class="bg-soft-primary rounded img-fluid avatar-40 me-3"></td>
													<td>{{ $exercise->title }}</td>
												</tr>
												@endforeach
											@else
												<tr>
													<td colspan="2">{{ __('message.not_found_entry', [ 'name' => __('message.exercise') ]) }}</td>
												</tr>
											@endif
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				@endif
				@if($auth_user->can('workout-list'))
					<div class="col-md-6">
						<div class="card" data-aos="fade-up" data-aos-delay="900">
							<div class="card-header d-flex justify-content-between flex-wrap">
								<div class="header-title">
									<h4 class="card-title">{{ __('message.list_form_title',['form' => __('message.workout')] ) }}</h4>
								</div>
								<div class="card-action">
									<a href="{{ route('workout.index') }}" data-bs-toggle="tooltip" title="{{ __('message.list_form_title',['form' => __('message.workout') ]) }}">{{ __('message.see_all') }}</a>
								</div>
							</div>
							<div class="card-body p-0">
								<div class="table-responsive mt-4 dashboard_table_list">
									<table id="basic-table" class="table table-striped mb-0" role="grid">
										<thead>
											<tr>
												<th>{{ __('message.image') }}</th>
												<th>{{ __('message.title') }}</th>	
											</tr>
										</thead>
										<tbody>
											@if( count($data['workout']) > 0 )
												@foreach ($data['workout'] as $workout)
												<tr>
													<td><img src="{{ getSingleMedia($workout, 'workout_image') }}" alt="workout-image" class="bg-soft-primary rounded img-fluid avatar-40 me-3"></td>
													<td>{{ $workout->title }}</td>
												</tr>
												@endforeach
											@else
												<tr>
													<td colspan="2">{{ __('message.not_found_entry', [ 'name' => __('message.workout') ]) }}</td>
												</tr>
											@endif
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				@endif
				@if($auth_user->can('diet-list'))
					<div class="col-md-6">
						<div class="card" data-aos="fade-up" data-aos-delay="1000">
							<div class="card-header d-flex justify-content-between flex-wrap">
								<div class="header-title">
									<h4 class="card-title">{{ __('message.list_form_title',['form' => __('message.diet')] ) }}</h4>
								</div>
								<div class="card-action">
									<a href="{{ route('diet.index') }}" data-bs-toggle="tooltip" title="{{ __('message.list_form_title', [ 'form' => __('message.diet') ]) }}">{{ __('message.see_all') }}</a>
								</div>
							</div>
							<div class="card-body p-0">
								<div class="table-responsive mt-4 dashboard_table_list">
									<table id="basic-table" class="table table-striped mb-0" role="grid">
										<thead>
											<tr>
												<th>{{ __('message.image') }}</th>
												<th>{{ __('message.title') }}</th>	
											</tr>
										</thead>
										<tbody>
											@if( count($data['diet']) > 0 )
												@foreach ($data['diet'] as $diet)
												<tr>
													<td><img src="{{ getSingleMedia($diet, 'diet_image') }}" alt="diet-image" class="bg-soft-primary rounded img-fluid avatar-40 me-3"></td>
													<td>{{ $diet->title }}</td>
												</tr>
												@endforeach
											@else
												<tr>
													<td colspan="2">{{ __('message.not_found_entry', [ 'name' => __('message.diet') ]) }}</td>
												</tr>
											@endif
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				@endif
				@if($auth_user->can('post-list'))
					<div class="col-md-6">
						<div class="card" data-aos="fade-up" data-aos-delay="1100">
							<div class="card-header d-flex justify-content-between flex-wrap">
								<div class="header-title">
									<h4 class="card-title">{{ __('message.list_form_title',['form' => __('message.post')] ) }}</h4>
								</div>
								<div class="card-action">
									<a href="{{ route('post.index') }}" data-bs-toggle="tooltip" title="{{ __('message.list_form_title', [ 'form' => __('message.post') ]) }}">{{ __('message.see_all') }}</a>
								</div>
							</div>
							<div class="card-body p-0">
								<div class="table-responsive mt-4 dashboard_table_list">
									<table id="basic-table" class="table table-striped mb-0" role="grid">
										<thead>
											<tr>
												<th>{{ __('message.image') }}</th>
												<th>{{ __('message.title') }}</th>	
											</tr>
										</thead>
										<tbody>
											@if( count($data['post']) > 0 )
												@foreach ($data['post'] as $post)
												<tr>
													<td><img src="{{ getSingleMedia($post, 'post_image') }}" alt="post-image" class="bg-soft-primary rounded img-fluid avatar-40 me-3"></td>
													<td>{{ $post->title }}</td>
												</tr>
												@endforeach
											@else
												<tr>
													<td colspan="2">{{ __('message.not_found_entry', [ 'name' => __('message.post') ]) }}</td>
												</tr>
											@endif
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				@endif
         	</div>
      	</div>
   	</div>
</x-app-layout>
