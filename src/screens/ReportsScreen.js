import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import * as Print from 'expo-print';
import { fetchReports } from '../api/client';
import EmptyState from '../components/EmptyState';
import { colors, layout } from '../theme/layout';
import { capitalizeWords } from '../collectionForm';

const VIEWS = [
  { id: 'periodic', label: 'Collections', icon: '▤' },
  { id: 'demographics', label: 'Age & Gender', icon: '◉' },
  { id: 'consumption', label: 'Consumption', icon: '♥' },
];

const AGE_ICON = '◷';

function escapeHtml(value) {
  return String(value ?? '—').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[character]);
}

function hasActivity(count, amount) {
  return Number(count) > 0 || Number(amount) > 0;
}

function activeRows(rows, countIndex, amountIndex) {
  return rows.filter((row) => hasActivity(row[countIndex], row[amountIndex]));
}

function printLines(entries, emptyMessage, columns, total) {
  if (entries.length === 0) {
    return `<p class="empty">${escapeHtml(emptyMessage)}</p>`;
  }

  const wide = columns.length > 3 ? ' lines wide' : ' lines';
  const head = `<div class="head">${columns.map((column) => `<span>${escapeHtml(column)}</span>`).join('')}</div>`;
  const lines = entries.map((entry) => {
    const isZero = entry.values.every((value) => Number(String(value).replace(/,/g, '')) === 0);

    return `
    <div class="line${isZero ? ' zero' : ''}">
      <span>${escapeHtml(entry.label)}${entry.detail ? `<small>${escapeHtml(entry.detail)}</small>` : ''}</span>
      ${entry.values.map((value) => `<strong>${escapeHtml(value)}</strong>`).join('')}
    </div>
  `;
  }).join('');
  const foot = total
    ? `<div class="line total"><span>${escapeHtml(total.label)}</span>${total.values.map((value) => `<strong>${escapeHtml(value)}</strong>`).join('')}</div>`
    : '';

  return `<div class="${wide.trim()}">${head}${lines}${foot}</div>`;
}

function sumColumn(rows, index) {
  return rows.reduce((total, row) => total + Number(row[index] || 0), 0);
}

function ageRows(age, peopleTotal) {
  const order = ['16–25', '26–35', '36–45', '46–55', '56+'];
  const byGroup = Object.fromEntries((age?.groups ?? []).map((group) => [group.group, group]));
  const total = peopleTotal ?? age?.totalPatients ?? age?.totalDonors ?? 0;

  return order.map((label) => {
    const group = byGroup[label] ?? { count: 0, bags: 0 };
    const share = group.percentage !== undefined
      ? group.percentage
      : (total > 0 ? Math.round((group.count / total) * 100) : 0);
    return [label, group.count ?? 0, group.bags ?? 0, `${share}%`];
  });
}

function genderRows(gender, includeTop = false) {
  const total = gender?.total ?? gender?.totalPatients ?? 0;
  return (gender?.rows ?? []).map((row) => {
    const share = row.percentage !== undefined
      ? row.percentage
      : (total > 0 ? Math.round((row.count / total) * 100) : 0);
    const cells = [row.gender, row.count ?? 0, row.bags ?? 0];
    if (includeTop) {
      cells.push(row.topBloodType || '—');
    }
    cells.push(`${share}%`);
    return cells;
  });
}

function buildReportPrintHtml(data, period, year) {
  const periods = data.printPeriods ?? {
    monthly: data.periodic,
    quarterly: { rows: [] },
    yearly: { rows: [] },
  };
  const monthlyRows = (periods.monthly?.rows ?? []).map((row) => [row.label, row.donors, row.bags]);
  const quarterlyRows = (periods.quarterly?.rows ?? []).map((row) => [row.label, row.detail, row.donors, row.bags]);
  const yearlyRows = (periods.yearly?.rows ?? []).map((row) => [row.label, row.donors, row.bags]);
  const emptyCollections = 'No collections recorded for this period';
  const emptyConsumption = 'No consumption recorded for this period';
  const consumptionDemographics = data.consumptionDemographics ?? {};
  const consumption = data.consumption ?? {};
  const location = data.reportLocation || 'All locations';
  const provinceSource = (consumption.provinces ?? []).filter((province) => hasActivity(province.patient_count, province.total_units));

  return `
    <!DOCTYPE html>
    <html>
      <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <style>
          @page { size: A4 portrait; margin: 10mm; }
          * { box-sizing: border-box; }
          body { margin: 0; font-family: Arial, sans-serif; color: #1e293b; font-size: 11px; }
          .page { break-after: avoid; page-break-after: avoid; }
          .mark { margin: 0 0 3px; text-align: right; color: #64748b; font-size: 9px; font-weight: bold; }
          header { border-bottom: 2px solid #800020; padding-bottom: 4px; margin-bottom: 6px; }
          h1 { margin: 0 0 2px; color: #800020; font-size: 15px; }
          h2 { margin: 8px 0 2px; color: #800020; font-size: 12px; }
          h3 { margin: 0 0 3px; color: #800020; font-size: 11px; }
          .lead, .meta { margin: 0 0 4px; color: #475569; font-size: 10px; }
          .split { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 8px 10px; align-items: stretch; }
          .stack { display: grid; grid-template-rows: auto 1fr; height: 100%; }
          .stack .panel:last-child { align-self: end; }
          .quad { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 10px; }
          .panel { border: 1px solid #eadfe2; border-radius: 8px; overflow: hidden; }
          .panel h3 { margin: 0; padding: 5px 8px; background: #800020; color: #fff; font-size: 10px; letter-spacing: 0.04em; text-transform: uppercase; }
          .lines { display: flex; flex-direction: column; }
          .head, .line { display: grid; grid-template-columns: minmax(0, 1fr) 3.6rem 3.6rem; gap: 6px; align-items: center; padding: 3px 8px; }
          .wide .head, .wide .line { grid-template-columns: minmax(0, 1.3fr) 5.4rem 4rem 3.6rem; }
          .head { color: #94a3b8; font-size: 8px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; border-bottom: 1px solid #f3e8ea; }
          .head span:not(:first-child), .line strong { text-align: right; font-variant-numeric: tabular-nums; }
          .line small { display: block; color: #64748b; font-size: 8px; font-weight: 600; }
          .line.zero strong { color: #94a3b8; font-weight: 600; }
          .line.total { background: #fbf6f7; border-bottom: 0; }
          .line.total span { color: #800020; font-weight: 800; }
          .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; width: min(100%, 160mm); margin: 14mm auto 0; padding-top: 8mm; border-top: 1px solid #cbd5e1; text-align: center; }
          .signatures p { margin: 0 0 12px; font-size: 11px; }
          .signatures span { display: block; color: #334155; font-size: 10px; }
          .empty { color: #64748b; }
        </style>
      </head>
      <body>
        <section class="page">
          <p class="mark">1 / 1</p>
          <header>
            <h1>CRH Blood Bank — Reports</h1>
            <p class="meta">${escapeHtml(location)} · Year ${escapeHtml(year)} · Generated ${escapeHtml(new Date().toLocaleDateString())}</p>
          </header>
          <h2>Periodic Collections — ${escapeHtml(year)}</h2>
          <p class="lead">Monthly, quarterly, and yearly totals for ${escapeHtml(year)}.</p>
          <div class="split">
            <div class="panel">
              <h3>Monthly</h3>
              ${printLines(monthlyRows.map((row) => ({ label: row[0], values: [String(row[1]), String(row[2])] })), emptyCollections, ['Month', 'Donors', 'Bags'], { label: 'Total', values: [String(sumColumn(monthlyRows, 1)), String(sumColumn(monthlyRows, 2))] })}
            </div>
            <div class="stack">
              <div class="panel">
                <h3>Quarterly</h3>
                ${printLines(quarterlyRows.map((row) => ({ label: row[0], detail: row[1], values: [String(row[2]), String(row[3])] })), emptyCollections, ['Quarter', 'Donors', 'Bags'], { label: 'Total', values: [String(sumColumn(quarterlyRows, 2)), String(sumColumn(quarterlyRows, 3))] })}
              </div>
              <div class="panel">
                <h3>Yearly</h3>
                ${printLines(activeRows(yearlyRows, 1, 2).map((row) => ({ label: row[0], values: [String(row[1]), String(row[2])] })), emptyCollections, ['Year', 'Donors', 'Bags'])}
              </div>
            </div>
          </div>
          <h2>Age &amp; Gender Demographics — ${escapeHtml(year)}</h2>
          <p class="lead">Collection and consumption, by age and by gender, for ${escapeHtml(year)}.</p>
          <div class="quad">
            <div class="panel">
              <h3>Blood collection — age</h3>
              ${printLines(activeRows(ageRows(data.ageReport, data.ageReport?.totalDonors), 1, 2).map((row) => ({ label: `${row[0]} years`, values: [String(row[1]), String(row[2])] })), emptyCollections, ['Age', 'Donors', 'Bags'])}
            </div>
            <div class="panel">
              <h3>Blood collection — gender</h3>
              ${printLines(activeRows(genderRows(data.gender, true), 1, 2).map((row) => ({ label: row[0], values: [String(row[1]), String(row[2])] })), emptyCollections, ['Gender', 'Donors', 'Bags'])}
            </div>
            <div class="panel">
              <h3>Blood consumption — age</h3>
              ${printLines(activeRows(ageRows(consumptionDemographics.age, consumptionDemographics.age?.totalPatients), 1, 2).map((row) => ({ label: `${row[0]} years`, values: [String(row[1]), String(row[2])] })), emptyConsumption, ['Age', 'Patients', 'Bags'])}
            </div>
            <div class="panel">
              <h3>Blood consumption — gender</h3>
              ${printLines(activeRows(genderRows(consumptionDemographics.gender), 1, 2).map((row) => ({ label: row[0], values: [String(row[1]), String(row[2])] })), emptyConsumption, ['Gender', 'Patients', 'Bags'])}
            </div>
          </div>
          <h2>Blood Consumption — ${escapeHtml(year)}</h2>
          <p class="lead">Units issued to patients in ${escapeHtml(year)}.</p>
          <div class="panel">
            <h3>By province</h3>
            ${printLines(provinceSource.map((province) => ({ label: province.name || 'Other locations', values: [String(province.municipality_count), String(province.patient_count), String(province.total_units)] })), emptyConsumption, ['Province', 'Municipalities', 'Records', 'Units'], { label: 'Total Consumed Units:', values: ['', '', String(consumption.totalUnits ?? 0)] })}
          </div>
          <div class="signatures">
            <div>
              <p>Prepared by: ____________________</p>
              <span>Blood Bank Staff</span>
            </div>
            <div>
              <p>Approved by: ____________________</p>
              <span>Medical Officer / Administrator</span>
            </div>
          </div>
        </section>
      </body>
    </html>
  `;
}

function SectionHeading({ icon, title, detail }) {
  return (
    <View style={styles.sectionHeading}>
      <View style={styles.sectionIcon}>
        <Text style={styles.sectionIconText}>{icon}</Text>
      </View>
      <View style={styles.sectionHeadingText}>
        <Text style={styles.sectionTitle}>{title}</Text>
        {detail ? <Text style={styles.sectionDetail}>{detail}</Text> : null}
      </View>
    </View>
  );
}

function AgeGroupList({ groups, peopleLabel, totalPeople }) {
  const visibleGroups = groups.filter((group) => Number(group.count) > 0);
  if (visibleGroups.length === 0) {
    return <Text style={styles.emptyInline}>No age data available.</Text>;
  }

  return (
    <View style={styles.metricList}>
      {visibleGroups.map((group) => {
        const percentage = totalPeople > 0 ? Math.round((group.count / totalPeople) * 100) : 0;
        return (
          <View key={group.group} style={styles.ageRow}>
            <View style={styles.ageIcon}>
              <Text style={styles.ageIconText}>{AGE_ICON}</Text>
            </View>
            <View style={styles.ageInfo}>
              <View style={styles.ageTopLine}>
                <Text style={styles.ageLabel}>{group.group} years</Text>
                <Text style={styles.ageCount}>{group.count} {peopleLabel}</Text>
              </View>
              <View style={styles.ageBottomLine}>
                <Text style={styles.ageMeta}>{group.bags} bags · {percentage}%</Text>
                <View style={styles.progressTrack}>
                  <View style={[styles.progressFill, { width: `${percentage}%` }]} />
                </View>
              </View>
            </View>
          </View>
        );
      })}
    </View>
  );
}

function GenderSummary({ rows, peopleLabel, totalPeople }) {
  return (
    <View style={styles.genderGrid}>
      {rows.map((row) => {
        const isFemale = row.gender.toLowerCase() === 'female';
        const percentage = row.percentage ?? (totalPeople > 0 ? Math.round((row.count / totalPeople) * 100) : 0);
        return (
          <View key={row.gender} style={styles.genderCard}>
            <View style={[styles.genderIcon, isFemale ? styles.femaleIcon : styles.maleIcon]}>
              <Text style={[styles.genderIconText, isFemale ? styles.femaleText : styles.maleText]}>
                {isFemale ? '♀' : '♂'}
              </Text>
            </View>
            <Text style={styles.genderLabel}>{row.gender}</Text>
            <Text style={styles.genderCount}>{row.count} {peopleLabel}</Text>
            <Text style={styles.genderMeta}>{percentage}% · {row.bags} bags</Text>
          </View>
        );
      })}
    </View>
  );
}

function SummaryCard({ icon, title, detail, children }) {
  return (
    <View style={styles.summaryCard}>
      <SectionHeading icon={icon} title={title} detail={detail} />
      {children}
    </View>
  );
}

export default function ReportsScreen() {
  const [view, setView] = useState('periodic');
  const [period, setPeriod] = useState('monthly');
  const [year, setYear] = useState(String(new Date().getFullYear()));
  const [location, setLocation] = useState('');
  const [appliedLocation, setAppliedLocation] = useState('');
  const [yearPickerVisible, setYearPickerVisible] = useState(false);
  const [expandedProvince, setExpandedProvince] = useState(null);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [printError, setPrintError] = useState('');
  const [printing, setPrinting] = useState(false);
  const [selectedPeriod, setSelectedPeriod] = useState(null);
  const requestId = useRef(0);

  const load = useCallback(async () => {
    const currentRequest = ++requestId.current;
    setLoading(true);
    setError('');
    setPrintError('');
    setData(null);
    try {
      const report = await fetchReports({
        view,
        period,
        year,
        month: 'all',
        location: appliedLocation,
        consumption_year: year,
        consumption_month: 'all',
      });
      if (requestId.current === currentRequest) {
        setData(report);
      }
    } catch (e) {
      if (requestId.current === currentRequest) {
        setError(e.message || 'Unable to load reports.');
      }
    } finally {
      if (requestId.current === currentRequest) {
        setLoading(false);
      }
    }
  }, [appliedLocation, view, period, year]);

  useEffect(() => {
    const timer = setTimeout(() => setAppliedLocation(location.trim()), 250);
    return () => clearTimeout(timer);
  }, [location]);

  useEffect(() => {
    load();
    return () => {
      requestId.current += 1;
    };
  }, [load]);

  const handlePrint = async () => {
    if (!data || loading || printing) {
      return;
    }

    setPrinting(true);
    setPrintError('');
    try {
      const report = await fetchReports({
        view,
        period,
        year,
        month: 'all',
        location: appliedLocation,
        consumption_year: year,
        consumption_month: 'all',
        print_all: 1,
      });
      await Print.printAsync({
        html: buildReportPrintHtml(report, period, year),
      });
    } catch (printRequestError) {
      setPrintError(printRequestError.message || 'Unable to open the print preview.');
    } finally {
      setPrinting(false);
    }
  };

  const collectionGroups = data?.ageReport?.groups ?? [];
  const collectionGender = data?.gender?.rows ?? [];
  const consumptionAge = data?.consumptionDemographics?.age;
  const consumptionGender = data?.consumptionDemographics?.gender;
  const yearOptions = data?.periodicYears
    ?? Array.from({ length: 7 }, (_, index) => String(new Date().getFullYear() - 3 + index));

  return (
    <ScrollView style={layout.screen} contentContainerStyle={styles.content}>
      <View style={styles.pageHeading}>
        <View style={styles.pageIcon}>
          <Text style={styles.pageIconText}>▤</Text>
        </View>
        <View style={styles.pageHeadingText}>
          <Text style={layout.screenTitle}>Reports & Analytics</Text>
          <Text style={styles.pageSubtitle}>Blood bank activity at a glance</Text>
        </View>
      </View>
      <View style={styles.searchBox}>
        <Text style={styles.searchIcon}>⌕</Text>
        <TextInput
          value={location}
          onChangeText={(value) => setLocation(capitalizeWords(value))}
          placeholder="Search location"
          placeholderTextColor="#94a3b8"
          autoCapitalize="words"
          autoCorrect={false}
          returnKeyType="search"
          accessibilityLabel="Search location"
          style={styles.searchInput}
        />
        {location !== '' ? (
          <Pressable accessibilityLabel="Clear location search" onPress={() => setLocation('')}>
            <Text style={styles.searchClear}>×</Text>
          </Pressable>
        ) : null}
      </View>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Print all reports"
        accessibilityState={{ disabled: loading || !data || printing }}
        disabled={loading || !data || printing}
        onPress={handlePrint}
        style={({ pressed }) => [
          styles.printButton,
          (loading || !data || printing) && styles.printButtonDisabled,
          pressed && styles.printButtonPressed,
        ]}
      >
        {printing
          ? <ActivityIndicator size="small" color={colors.primary} />
          : <Text style={styles.printButtonText}>Print all reports</Text>}
      </Pressable>

      <View style={styles.tabs}>
        {VIEWS.map((item) => {
          const active = view === item.id;
          return (
            <Pressable
              key={item.id}
              accessibilityRole="tab"
              accessibilityState={{ selected: active }}
              style={[styles.tab, active && styles.tabActive]}
              onPress={() => setView(item.id)}
            >
              <Text style={[styles.tabIcon, active && styles.tabIconActive]}>{item.icon}</Text>
              <Text numberOfLines={1} style={active ? styles.tabTextActive : styles.tabText}>
                {item.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {view === 'periodic' ? (
        <View style={styles.periodRow}>
          {['monthly', 'quarterly', 'yearly'].map((item) => {
            const active = period === item;
            return (
              <Pressable
                key={item}
                accessibilityRole="button"
                accessibilityState={{ selected: active }}
                style={[
                  styles.periodChip,
                  item === 'yearly' && styles.yearlyChip,
                  active && styles.periodChipActive,
                ]}
                onPress={() => setPeriod(item)}
              >
                <Text style={active ? styles.periodTextActive : styles.periodText}>
                  {item.charAt(0).toUpperCase() + item.slice(1)}
                </Text>
              </Pressable>
            );
          })}
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={`Select report year, currently ${year}`}
            accessibilityState={{ expanded: yearPickerVisible }}
            style={styles.yearSelector}
            onPress={() => setYearPickerVisible(true)}
          >
            <Text style={styles.yearSelectorText}>{year}</Text>
            <Text style={styles.yearSelectorArrow}>⌄</Text>
          </Pressable>
        </View>
      ) : null}

      <Modal
        visible={yearPickerVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setYearPickerVisible(false)}
      >
        <Pressable style={styles.yearModalBackdrop} onPress={() => setYearPickerVisible(false)}>
          <View style={styles.yearModal}>
            <Text style={styles.yearModalTitle}>Select year</Text>
            <ScrollView style={styles.yearOptions}>
              {yearOptions.map((option) => {
                const optionYear = String(option);
                const active = optionYear === year;
                return (
                  <Pressable
                    key={optionYear}
                    accessibilityRole="button"
                    accessibilityState={{ selected: active }}
                    style={[styles.yearOption, active && styles.yearOptionActive]}
                    onPress={() => {
                      setYear(optionYear);
                      setYearPickerVisible(false);
                    }}
                  >
                    <Text style={[styles.yearOptionText, active && styles.yearOptionTextActive]}>
                      {optionYear}
                    </Text>
                    {active ? <Text style={styles.yearCheck}>✓</Text> : null}
                  </Pressable>
                );
              })}
            </ScrollView>
          </View>
        </Pressable>
      </Modal>

      <Modal
        visible={selectedPeriod !== null}
        transparent
        animationType="slide"
        onRequestClose={() => setSelectedPeriod(null)}
      >
        <View style={styles.periodModalBackdrop}>
          <View style={styles.periodModal}>
            <View style={styles.periodModalHeader}>
              <View style={styles.sectionHeadingText}>
                <Text style={styles.periodModalTitle}>{selectedPeriod?.label} collections</Text>
                <Text style={styles.sectionDetail}>{selectedPeriod?.detail}</Text>
              </View>
              <Pressable accessibilityLabel="Close collection details" onPress={() => setSelectedPeriod(null)}>
                <Text style={styles.periodModalClose}>×</Text>
              </Pressable>
            </View>
            <Text style={styles.periodModalSummary}>
              {selectedPeriod?.donors ?? 0} donors · {selectedPeriod?.bags ?? 0} bags
            </Text>
            <ScrollView style={styles.periodRecords}>
              {(selectedPeriod?.records ?? []).map((record, index) => (
                <View key={`${record.name}-${index}`} style={styles.periodRecord}>
                  <Text style={styles.periodRecordName}>{record.name}</Text>
                  <Text style={styles.periodRecordMeta}>
                    {record.blood_type || 'Blood type not recorded'} · {record.age ?? '—'} years
                  </Text>
                  <Text style={styles.periodRecordMeta}>{record.address || 'Address not recorded'}</Text>
                  <Text style={styles.periodRecordMeta}>
                    {record.contact || 'No contact'} · {record.date || 'Date not recorded'} · {record.bags} bags
                  </Text>
                </View>
              ))}
              {(selectedPeriod?.records ?? []).length === 0 ? (
                <Text style={styles.emptyInline}>No donor records for this period.</Text>
              ) : null}
            </ScrollView>
          </View>
        </View>
      </Modal>

      {loading ? <ActivityIndicator color={colors.primary} style={styles.loading} /> : null}
      {error ? <Text accessibilityRole="alert" style={styles.error}>{error}</Text> : null}
      {printError ? <Text accessibilityRole="alert" style={styles.error}>{printError}</Text> : null}

      {data && view === 'periodic' ? (
        <>
          {(data.periodic?.rows ?? []).length === 0 ? (
            <EmptyState title="No periods" message="No collection data for this range." />
          ) : null}
          {(data.periodic?.rows ?? []).map((row) => (
            <Pressable
              key={row.id}
              accessibilityRole="button"
              accessibilityLabel={`View ${row.label} collection records`}
              style={[
                styles.periodCard,
                period === 'yearly' && String(row.label) === year && styles.periodCardSelected,
              ]}
              onPress={() => setSelectedPeriod(row)}
            >
              <SectionHeading icon="▤" title={row.label} detail={row.detail} />
              <Text style={styles.periodStats}>Donors {row.donors} <Text style={styles.dot}>·</Text> Bags {row.bags}</Text>
            </Pressable>
          ))}
        </>
      ) : null}

      {data && view === 'demographics' ? (
        <View style={styles.reportSections}>
          <SummaryCard
            icon="🩸"
            title="Blood Collection"
            detail={`${data.ageReport?.totalDonors ?? 0} unique donors`}
          >
            <View style={styles.demoPair}>
              <View style={styles.demoPairCol}>
                <Text style={styles.subheading}>Age group distribution</Text>
                <AgeGroupList
                  groups={collectionGroups}
                  peopleLabel="donors"
                  totalPeople={data.ageReport?.totalDonors ?? 0}
                />
              </View>
              <View style={styles.demoPairCol}>
                <Text style={styles.subheading}>Gender comparison</Text>
                <GenderSummary
                  rows={collectionGender}
                  peopleLabel="donors"
                  totalPeople={data.gender?.total ?? 0}
                />
              </View>
            </View>
          </SummaryCard>

          <SummaryCard
            icon="♥"
            title="Blood Consumption"
            detail={`${consumptionAge?.totalPatients ?? 0} unique patients`}
          >
            <View style={styles.demoPair}>
              <View style={styles.demoPairCol}>
                <Text style={styles.subheading}>Age group distribution</Text>
                <AgeGroupList
                  groups={consumptionAge?.groups ?? []}
                  peopleLabel="patients"
                  totalPeople={consumptionAge?.totalPatients ?? 0}
                />
              </View>
              <View style={styles.demoPairCol}>
                <Text style={styles.subheading}>Gender comparison</Text>
                <GenderSummary
                  rows={consumptionGender?.rows ?? []}
                  peopleLabel="patients"
                  totalPeople={consumptionGender?.totalPatients ?? 0}
                />
              </View>
            </View>
          </SummaryCard>
        </View>
      ) : null}

      {data && view === 'consumption' ? (
        <SummaryCard
          icon="♥"
          title="Blood Consumption"
          detail={`${data.consumption?.provinceCount ?? 0} provinces`}
        >
          <View style={styles.totalUnits}>
            <Text style={styles.totalUnitsLabel}>Total units consumed</Text>
            <Text style={styles.totalUnitsValue}>{data.consumption?.totalUnits ?? 0}</Text>
          </View>
          {(data.consumption?.byBloodType ?? []).map((row) => (
            <View key={row.type} style={styles.bloodTypeRow}>
              <View style={styles.bloodTypeIcon}>
                <Text style={styles.bloodTypeIconText}>♥</Text>
              </View>
              <Text style={styles.bloodTypeLabel}>{row.type || 'Unknown'}</Text>
              <Text style={styles.bloodTypeCount}>{row.total} units</Text>
            </View>
          ))}
          <Text style={styles.subheading}>Consumption by province</Text>
          {(data.consumption?.provinces ?? []).map((province) => {
            const expanded = expandedProvince === province.name;

            return (
              <View key={province.name} style={styles.provinceCard}>
                <Pressable
                  accessibilityRole="button"
                  accessibilityState={{ expanded }}
                  style={styles.provinceHeader}
                  onPress={() => setExpandedProvince(expanded ? null : province.name)}
                >
                  <View style={styles.provinceIcon}>
                    <Text style={styles.provinceIconText}>⌖</Text>
                  </View>
                  <View style={styles.provinceInfo}>
                    <Text style={styles.provinceName}>{province.name || 'Other locations'}</Text>
                    <Text style={styles.provinceDetail}>
                      {province.municipality_count} municipalities · {province.patient_count} records
                    </Text>
                  </View>
                  <View style={styles.provinceTotal}>
                    <Text style={styles.provinceUnits}>{province.total_units}</Text>
                    <Text style={styles.provinceUnitsLabel}>units</Text>
                  </View>
                  <Text style={styles.provinceChevron}>{expanded ? '⌃' : '›'}</Text>
                </Pressable>
                {expanded ? (
                  <View style={styles.municipalityList}>
                    {(province.municipalities ?? []).map((municipality) => (
                      <View key={municipality.name} style={styles.municipalityRow}>
                        <Text style={styles.municipalityName}>{municipality.name || 'Unknown municipality'}</Text>
                        <Text style={styles.municipalityStats}>
                          {municipality.total_units} units · {municipality.patient_count} records
                        </Text>
                      </View>
                    ))}
                  </View>
                ) : null}
              </View>
            );
          })}
        </SummaryCard>
      ) : null}

      <Pressable
        accessibilityRole="button"
        style={styles.refresh}
        onPress={load}
      >
        <Text style={styles.refreshIcon}>↻</Text>
        <Text style={styles.refreshText}>Refresh reports</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  content: { paddingBottom: 28 },
  pageHeading: { flexDirection: 'row', alignItems: 'center', gap: 9, marginBottom: 10 },
  pageIcon: { width: 42, height: 42, borderRadius: 14, backgroundColor: '#fff1f1', alignItems: 'center', justifyContent: 'center' },
  pageIconText: { color: colors.primary, fontSize: 22, fontWeight: '800' },
  pageHeadingText: { flex: 1, minWidth: 0 },
  pageSubtitle: { marginTop: 3, color: colors.muted, fontSize: 12 },
  searchBox: {
    minHeight: 42,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 11,
    backgroundColor: '#fff',
  },
  searchIcon: { color: colors.primary, fontSize: 18, lineHeight: 20 },
  searchInput: { flex: 1, minHeight: 40, paddingVertical: 0, color: colors.text, fontSize: 14 },
  searchClear: { color: colors.muted, fontSize: 22, lineHeight: 24, paddingHorizontal: 4 },
  printButton: {
    minHeight: 42,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: '#e7b4b4',
    borderRadius: 11,
    backgroundColor: '#fff1f1',
  },
  printButtonText: { color: colors.primary, fontSize: 12, fontWeight: '800' },
  printButtonDisabled: { opacity: 0.45 },
  printButtonPressed: { opacity: 0.7 },
  tabs: { flexDirection: 'row', gap: 7, marginBottom: 14 },
  tab: { flex: 1, minWidth: 0, minHeight: 62, paddingVertical: 8, paddingHorizontal: 3, borderWidth: 1, borderColor: colors.border, borderRadius: 12, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center' },
  tabActive: { backgroundColor: '#fff1f1', borderColor: '#e7b4b4' },
  tabIcon: { color: colors.muted, fontSize: 18, lineHeight: 22, marginBottom: 2 },
  tabIconActive: { color: colors.primary },
  tabText: { color: colors.muted, fontWeight: '600', fontSize: 10, textAlign: 'center' },
  tabTextActive: { color: colors.primary, fontWeight: '800', fontSize: 10, textAlign: 'center' },
  periodRow: { flexDirection: 'row', gap: 6, marginBottom: 12 },
  periodChip: { flex: 1, minWidth: 0, minHeight: 38, borderWidth: 1, borderColor: colors.border, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  yearlyChip: { flex: 0.82 },
  periodChipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  periodText: { color: colors.text, fontSize: 12, fontWeight: '600' },
  periodTextActive: { color: '#fff', fontSize: 12, fontWeight: '700' },
  yearSelector: {
    width: 76,
    minHeight: 38,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    backgroundColor: '#fff',
  },
  yearSelectorText: { color: colors.text, fontSize: 12, fontWeight: '700' },
  yearSelectorArrow: { color: colors.muted, fontSize: 16, lineHeight: 18 },
  yearModalBackdrop: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: 'rgba(15, 23, 42, 0.4)',
  },
  yearModal: {
    width: '100%',
    maxWidth: 320,
    maxHeight: '70%',
    padding: 16,
    borderRadius: 14,
    backgroundColor: '#fff',
    elevation: 8,
  },
  yearModalTitle: { marginBottom: 10, color: colors.text, fontSize: 16, fontWeight: '800' },
  yearOptions: { flexShrink: 1 },
  yearOption: {
    minHeight: 42,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 11,
    borderRadius: 9,
  },
  yearOptionActive: { backgroundColor: '#fff1f1' },
  yearOptionText: { color: colors.text, fontSize: 14, fontWeight: '600' },
  yearOptionTextActive: { color: colors.primary, fontWeight: '800' },
  yearCheck: { color: colors.primary, fontWeight: '800' },
  periodModalBackdrop: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(15, 23, 42, 0.42)' },
  periodModal: { maxHeight: '82%', padding: 16, borderTopLeftRadius: 20, borderTopRightRadius: 20, backgroundColor: '#fff' },
  periodModalHeader: { flexDirection: 'row', alignItems: 'center', paddingBottom: 10, borderBottomWidth: 1, borderBottomColor: colors.border },
  periodModalTitle: { color: colors.text, fontSize: 17, fontWeight: '800' },
  periodModalClose: { color: colors.muted, fontSize: 27, lineHeight: 30, paddingHorizontal: 5 },
  periodModalSummary: { color: colors.primary, fontSize: 12, fontWeight: '800', paddingVertical: 11 },
  periodRecords: { flexShrink: 1 },
  periodRecord: { gap: 4, padding: 11, marginBottom: 8, borderWidth: 1, borderColor: colors.border, borderRadius: 11, backgroundColor: '#fffafa' },
  periodRecordName: { color: colors.text, fontSize: 13, fontWeight: '800' },
  periodRecordMeta: { color: colors.muted, fontSize: 11, lineHeight: 15 },
  loading: { marginVertical: 18 },
  error: { color: colors.danger, marginBottom: 10, fontSize: 13 },
  reportSections: { gap: 12 },
  demoPair: { flexDirection: 'column', gap: 10, alignItems: 'stretch' },
  demoPairCol: { width: '100%' },
  summaryCard: { padding: 13, borderWidth: 1, borderColor: '#f1dede', borderRadius: 16, backgroundColor: '#fff', shadowColor: '#7f1d1d', shadowOpacity: 0.04, shadowRadius: 8, shadowOffset: { width: 0, height: 3 }, elevation: 1 },
  sectionHeading: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingBottom: 11, borderBottomWidth: 1, borderBottomColor: '#f1f5f9' },
  sectionIcon: { width: 36, height: 36, borderRadius: 12, backgroundColor: '#fff1f1', alignItems: 'center', justifyContent: 'center' },
  sectionIconText: { fontSize: 18, color: colors.primary },
  sectionHeadingText: { flex: 1 },
  sectionTitle: { color: colors.text, fontSize: 15, fontWeight: '800' },
  sectionDetail: { color: colors.muted, fontSize: 11, marginTop: 2 },
  subheading: { color: colors.text, fontSize: 12, fontWeight: '800', marginTop: 13, marginBottom: 8 },
  metricList: { gap: 7 },
  ageRow: { flexDirection: 'row', alignItems: 'center', gap: 9, padding: 9, borderRadius: 12, backgroundColor: '#fafafa' },
  ageIcon: { width: 30, height: 30, borderRadius: 10, backgroundColor: '#f1f5f9', alignItems: 'center', justifyContent: 'center' },
  ageIconText: { fontSize: 16, color: colors.primary, fontWeight: '700' },
  ageInfo: { flex: 1 },
  ageTopLine: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 6 },
  ageLabel: { color: colors.text, fontWeight: '700', fontSize: 12 },
  ageCount: { color: colors.primary, fontWeight: '800', fontSize: 11 },
  ageBottomLine: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 4 },
  ageMeta: { color: colors.muted, fontSize: 10, minWidth: 76 },
  progressTrack: { flex: 1, height: 5, borderRadius: 4, backgroundColor: '#e2e8f0', overflow: 'hidden' },
  progressFill: { height: '100%', borderRadius: 4, backgroundColor: colors.primary },
  genderGrid: { flexDirection: 'row', gap: 8 },
  genderCard: { flex: 1, minWidth: 0, padding: 10, borderRadius: 12, backgroundColor: '#fafafa', alignItems: 'center' },
  genderIcon: { width: 34, height: 34, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginBottom: 6 },
  femaleIcon: { backgroundColor: '#fff0f5' },
  maleIcon: { backgroundColor: '#eff6ff' },
  genderIconText: { fontSize: 21, fontWeight: '700' },
  femaleText: { color: '#db2777' },
  maleText: { color: '#2563eb' },
  genderLabel: { color: colors.text, fontWeight: '800', fontSize: 12 },
  genderCount: { color: colors.primary, fontWeight: '800', fontSize: 12, marginTop: 3 },
  genderMeta: { color: colors.muted, fontSize: 10, marginTop: 2, textAlign: 'center' },
  emptyInline: { color: colors.muted, fontSize: 12, paddingVertical: 8 },
  periodCard: { padding: 13, borderWidth: 1, borderColor: colors.border, borderRadius: 14, marginBottom: 9 },
  periodCardSelected: { borderColor: colors.primary, backgroundColor: '#fffafa' },
  periodStats: { marginTop: 9, color: colors.muted, fontSize: 12, fontWeight: '600' },
  dot: { color: colors.primary, fontWeight: '800' },
  totalUnits: { paddingVertical: 14, alignItems: 'center', borderBottomWidth: 1, borderBottomColor: '#f1f5f9', marginBottom: 4 },
  totalUnitsLabel: { color: colors.muted, fontSize: 12 },
  totalUnitsValue: { color: colors.primary, fontSize: 26, fontWeight: '900', marginTop: 3 },
  bloodTypeRow: { flexDirection: 'row', alignItems: 'center', gap: 9, paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: '#f8fafc' },
  bloodTypeIcon: { width: 30, height: 30, borderRadius: 10, backgroundColor: '#fff1f1', alignItems: 'center', justifyContent: 'center' },
  bloodTypeIconText: { color: colors.primary, fontSize: 15 },
  bloodTypeLabel: { flex: 1, color: colors.text, fontSize: 13, fontWeight: '700' },
  bloodTypeCount: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  provinceCard: {
    marginBottom: 7,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    backgroundColor: '#fff',
  },
  provinceHeader: {
    minHeight: 62,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
    paddingHorizontal: 9,
    paddingVertical: 8,
  },
  provinceIcon: {
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    backgroundColor: '#fff1f1',
  },
  provinceIconText: { color: colors.primary, fontSize: 17, fontWeight: '800' },
  provinceInfo: { flex: 1, minWidth: 0 },
  provinceName: { color: colors.text, fontSize: 12, fontWeight: '800' },
  provinceDetail: { marginTop: 3, color: colors.muted, fontSize: 9 },
  provinceTotal: { alignItems: 'flex-end' },
  provinceUnits: { color: colors.primary, fontSize: 14, fontWeight: '900' },
  provinceUnitsLabel: { color: colors.muted, fontSize: 9 },
  provinceChevron: { width: 14, color: colors.muted, fontSize: 18, textAlign: 'center' },
  municipalityList: {
    paddingHorizontal: 12,
    paddingBottom: 4,
    backgroundColor: '#fafafa',
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
  },
  municipalityRow: {
    minHeight: 34,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#eef1f4',
  },
  municipalityName: { flex: 1, color: colors.text, fontSize: 11, fontWeight: '600' },
  municipalityStats: { color: colors.muted, fontSize: 10, fontWeight: '600' },
  refresh: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, alignSelf: 'center', marginTop: 18, paddingVertical: 10, paddingHorizontal: 14 },
  refreshIcon: { color: colors.primary, fontSize: 17, fontWeight: '800' },
  refreshText: { color: colors.primary, fontWeight: '700', fontSize: 12 },
});
