/*!
This file is part of Live Carts for WooCommerce. For copyright and licensing information, please see ../../license/license.txt
*/

(function() {
	function CartsReport(props) {
		var charts = {
			convert_rate: {
				key: 'convert_rate',
				label: wp.i18n.__('Cart conversion rate', 'live-carts-for-woocommerce'),
				type: 'percent'
			},
			abandon_rate: {
				key: 'abandon_rate',
				label: wp.i18n.__('Cart abandonment rate', 'live-carts-for-woocommerce'),
				type: 'percent'
			},
			cart_value: {
				key: 'cart_value',
				label: wp.i18n.__('Average cart value', 'live-carts-for-woocommerce'),
				type: 'currency'
			}
		};
		var selectedChart = props.query && charts[props.query.chart] ? charts[props.query.chart] : charts.convert_rate;
		
		class ReportSummary extends React.Component {
			render() {
				var props = this.props;
				return React.createElement(
					wc.components.SummaryList,
					null,
					function() {	
						return Object.values(charts).map( function(chart) {
							var currentValue = props.summaryData && props.summaryData.totals.primary ? props.summaryData.totals.primary[ chart.key ] : 0;
							var prevValue = props.summaryData && props.summaryData.totals.secondary ? props.summaryData.totals.secondary[ chart.key ] : 0;
							var valueFormat = chart.type === 'percent'
								? function(val) {
									return (Math.round(val * 1000) / 10) + '%';
								  }
								: wc.currency.default().formatAmount;
							return React.createElement(
								wc.components.SummaryNumber,
								{
									key: chart.key,
									href: wc.navigation.getNewPath({ chart: chart.key }),
									value: valueFormat(currentValue),
									prevValue: valueFormat(prevValue),
									delta: wc.number.calculateDelta(currentValue, prevValue),
									label: chart.label,
									prevLabel: wp.i18n.__('Previous period:', 'live-carts-for-woocommerce'),
									selected: (props.query.chart ? props.query.chart : 'convert_rate') === chart.key,
									reverseTrend: chart.isReverseTrend,
									labelTooltipText: chart.labelTooltipText
								}
							);
						} );
					}
				);
			}
		}

		return [
			React.createElement(
				wc.components.ReportFilters,
				{
					query: props.query,
					path: props.path,
					report: 'phplugins-carts',
					filters: [{
						showFilters: function() {
							return false;
						},
					}]
				}
			),
			React.createElement(
				wp.compose.compose(
					wp.data.withSelect( function(sel, props) {
						var settings = sel(wc.data.SETTINGS_STORE_NAME).getSetting('wc_admin', 'wcAdminSettings');
						return {
							summaryData: wc.data.getSummaryNumbers({
								endpoint: 'phplugins-carts',
								query: props.query,
								select: sel,
								filters: [],
								defaultDateRange: settings.woocommerce_default_date_range,
								fields: Object.keys(charts)
							}),
							defaultDateRange: settings.woocommerce_default_date_range
						};
					} )
				)( ReportSummary ),
				{
					charts: charts,
					endpoint: 'phplugins-carts',
					isRequesting: props.isRequesting,
					query: props.query,
					selectedChart: selectedChart,
					filters: []
				}
			)
		];
	}

	wp.hooks.addFilter('woocommerce_admin_reports_list', 'phplugins/liveCarts/wcReportsList', function(reports) {
		reports.push({
			report: 'phplugins-carts',
			title: wp.i18n.__('Carts', 'live-carts-for-woocommerce'),
			component: CartsReport,
			navArgs: {
				id: 'phplugins-carts-analytics'
			}
		});
		return reports;
	});
})();