import { useCallback, useState } from 'react';
import {
  Alert,
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { deleteDonor, fetchDonor } from '../api/client';
import { colors } from '../theme/layout';

export default function DonorDetailScreen({ navigation, route }) {
  const { donorId } = route.params;
  const [donor, setDonor] = useState(null);
  const [loading, setLoading] = useState(true);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setDonor(await fetchDonor(donorId));
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, [donorId]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  if (loading) {
    return <View style={styles.center}><ActivityIndicator color="#990000" /></View>;
  }
  if (error || !donor) {
    return <View style={styles.center}><Text style={styles.error}>{error || 'Donor not found.'}</Text></View>;
  }

  const latestDonation = donor.latest_donation;
  const donations = donor.donations ?? [];

  const confirmDelete = () => {
    Alert.alert(
      'Delete this donor?',
      `${donor.name} and their collection history will be permanently removed.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            setDeleting(true);
            setActionError('');
            try {
              await deleteDonor(donorId);
              navigation.goBack();
            } catch (requestError) {
              setActionError(requestError.message);
              setDeleting(false);
            }
          },
        },
      ],
    );
  };

  return (
    <ScrollView style={styles.root} contentContainerStyle={styles.content}>
      <View style={styles.profileHeader}>
        <View style={styles.profileTitle}>
          <Text style={styles.title}>{donor.name}</Text>
          <Text style={styles.address}>{donor.address || 'Address not available'}</Text>
        </View>
        <View style={styles.bloodBadge}>
          <Text style={styles.bloodBadgeText}>{donor.blood_type || '—'}</Text>
        </View>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Donor information</Text>
        <View style={styles.infoGrid}>
          <InfoField label="Age" value={donor.age ? `${donor.age} yrs old` : '—'} />
          <InfoField label="Sex" value={donor.gender || '—'} />
          <InfoField label="Contact" value={donor.contact || '—'} />
          <InfoField label="Registered date" value={donor.registered_date || '—'} />
          <InfoField label="Donor address" value={donor.address || '—'} />
        </View>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Latest screening</Text>
        {latestDonation ? (
          <View style={styles.infoGrid}>
            <InfoField label="MBD / Date" value={latestDonation.mbd_date || '—'} wide />
            <InfoField label="Status" value={latestDonation.status || '—'} />
            <InfoField label="Serial #" value={latestDonation.serial_number || '—'} />
            <InfoField label="Reserved for Patient" value={latestDonation.reserved_for_px || '—'} />
            <InfoField label="Reason for Deferral" value={latestDonation.reason_for_deferral || '—'} />
            <InfoField label="Remarks" value={latestDonation.remarks || '—'} wide />
          </View>
        ) : (
          <Text style={styles.noHistory}>No screening or donation record is available.</Text>
        )}
      </View>

      <View style={styles.totalBanner}>
        <Text style={styles.totalLabel}>Total number of donations</Text>
        <Text style={styles.totalCount}>{donor.donation_count ?? donations.length}</Text>
      </View>

      <View style={styles.historyHeading}>
        <Text style={styles.sectionTitle}>Complete donation history</Text>
      </View>
      {donations.length > 0 ? (
        <ScrollView horizontal showsHorizontalScrollIndicator style={styles.historyScroll}>
          <View style={styles.historyTable}>
            <View style={[styles.historyRow, styles.historyHeader]}>
              <Text style={[styles.historyCell, styles.dateColumn, styles.headerText]}>Date</Text>
              <Text style={[styles.historyCell, styles.bloodColumn, styles.headerText]}>Blood Type</Text>
              <Text style={[styles.historyCell, styles.venueColumn, styles.headerText]}>MBD / Date</Text>
              <Text style={[styles.historyCell, styles.bagsColumn, styles.headerText]}>Bags</Text>
              <Text style={[styles.historyCell, styles.statusColumn, styles.headerText]}>Status</Text>
            </View>
            {donations.map((donation, index) => {
              const isLatest = index === 0;

              return (
                <View
                  key={donation.id}
                  style={[styles.historyRow, isLatest && styles.latestHistoryRow]}
                >
                  <View style={[styles.historyCell, styles.dateColumn]}>
                    <Text style={[styles.cellValue, isLatest && styles.latestCellValue]}>
                      {donation.date}
                    </Text>
                    {isLatest ? <Text style={styles.latestBadge}>new update</Text> : null}
                  </View>
                  <Text style={[styles.historyCell, styles.bloodColumn, styles.bloodTypeValue]}>
                    {donation.blood_type || donor.blood_type || '—'}
                  </Text>
                  <Text style={[styles.historyCell, styles.venueColumn, styles.cellValue]}>
                    {donation.mbd_date || '—'}
                  </Text>
                  <Text style={[styles.historyCell, styles.bagsColumn, styles.cellValue]}>
                    {String(donation.bags ?? 1)}
                  </Text>
                  <View style={[styles.historyCell, styles.statusColumn]}>
                    <StatusBadge status={donation.status} />
                  </View>
                </View>
              );
            })}
          </View>
        </ScrollView>
      ) : (
        <Text style={styles.noHistory}>No donation history available.</Text>
      )}

      <View style={styles.profileActions}>
        <Pressable
          style={styles.editProfileButton}
          onPress={() => navigation.navigate('RegisterDonor', { donorId })}
          accessibilityRole="button"
        >
          <Text style={styles.editProfileText}>Edit Donor</Text>
        </Pressable>
        <Pressable
          style={styles.deleteProfileButton}
          onPress={confirmDelete}
          disabled={deleting}
          accessibilityRole="button"
        >
          <Text style={styles.deleteProfileText}>{deleting ? 'Deleting…' : 'Delete Donor'}</Text>
        </Pressable>
      </View>
      {actionError ? <Text style={styles.error}>{actionError}</Text> : null}
    </ScrollView>
  );
}

function InfoField({ label, value, wide = false }) {
  return (
    <View style={[styles.infoField, wide && styles.infoFieldWide]}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value || '—'}</Text>
    </View>
  );
}

function StatusBadge({ status }) {
  const deferred = status === 'Deferred';
  return (
    <View style={[styles.statusBadge, deferred ? styles.deferredBadge : styles.passedBadge]}>
      <Text style={[styles.statusText, deferred ? styles.deferredText : styles.passedText]}>
        {status || 'Completed'}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f8fafc' },
  content: { padding: 16, paddingBottom: 28, gap: 12 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: '#fff' },
  error: { color: '#dc2626', textAlign: 'center', fontSize: 14 },
  profileHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
    padding: 16,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#edf1f5',
    backgroundColor: '#fff',
  },
  profileTitle: { flex: 1, minWidth: 0 },
  title: { fontSize: 19, lineHeight: 25, fontWeight: '800', color: '#1e293b' },
  address: { color: '#64748b', fontSize: 13, lineHeight: 18, marginTop: 4 },
  bloodBadge: {
    minWidth: 44,
    height: 40,
    paddingHorizontal: 7,
    borderRadius: 10,
    backgroundColor: '#fff0f0',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bloodBadgeText: { color: '#990000', fontWeight: '800', fontSize: 15 },
  card: {
    padding: 15,
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#edf1f5',
  },
  sectionTitle: { color: '#1e293b', fontSize: 15, fontWeight: '800', marginBottom: 12 },
  infoGrid: { flexDirection: 'row', flexWrap: 'wrap', rowGap: 15 },
  infoField: { width: '50%', paddingRight: 8 },
  infoFieldWide: { width: '100%', paddingRight: 0 },
  infoLabel: { color: '#64748b', fontSize: 12, marginBottom: 4 },
  infoValue: { color: '#1e293b', fontSize: 13, lineHeight: 18, fontWeight: '700' },
  noHistory: { color: '#64748b', fontSize: 13, lineHeight: 18 },
  totalBanner: {
    minHeight: 58,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 15,
    paddingVertical: 10,
    backgroundColor: '#fff',
    borderRadius: 13,
    borderWidth: 1,
    borderColor: '#dbe3ee',
  },
  totalLabel: { color: '#1e293b', fontSize: 14, fontWeight: '600', flex: 1, paddingRight: 8 },
  totalCount: { color: '#990000', fontSize: 20, fontWeight: '800' },
  historyHeading: { paddingTop: 3 },
  historyScroll: {
    borderWidth: 1,
    borderColor: '#dbe3ee',
    borderRadius: 11,
    backgroundColor: '#fff',
  },
  historyTable: { minWidth: 650 },
  historyRow: {
    flexDirection: 'row',
    alignItems: 'stretch',
    minHeight: 54,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  historyHeader: { minHeight: 38, backgroundColor: '#f8fafc' },
  latestHistoryRow: { backgroundColor: '#fff0f0', borderBottomColor: '#f3d1d1' },
  historyCell: {
    justifyContent: 'center',
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  dateColumn: { width: 128 },
  bloodColumn: { width: 92 },
  venueColumn: { width: 320 },
  bagsColumn: { width: 48 },
  statusColumn: { width: 78, alignItems: 'flex-start' },
  headerText: { color: '#334155', fontSize: 11, fontWeight: '800' },
  cellValue: { color: '#1e293b', fontSize: 12, lineHeight: 17 },
  latestCellValue: { fontWeight: '800' },
  bloodTypeValue: { color: '#990000', fontSize: 11, fontWeight: '800' },
  latestBadge: {
    color: '#990000',
    fontSize: 10,
    fontWeight: '800',
    marginTop: 4,
  },
  statusBadge: { borderRadius: 10, paddingHorizontal: 8, paddingVertical: 4 },
  passedBadge: { backgroundColor: '#ecfdf5' },
  deferredBadge: { backgroundColor: '#fff7ed' },
  statusText: { fontSize: 10, fontWeight: '800' },
  passedText: { color: '#047857' },
  deferredText: { color: '#c2410c' },
  profileActions: {
    flexDirection: 'row',
    gap: 9,
    marginTop: 2,
  },
  editProfileButton: {
    flex: 1,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#dbe3ee',
    borderRadius: 10,
    backgroundColor: '#fff',
  },
  editProfileText: { color: '#475569', fontSize: 13, fontWeight: '700' },
  deleteProfileButton: {
    flex: 1,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#fecaca',
    borderRadius: 10,
    backgroundColor: '#fff5f5',
  },
  deleteProfileText: { color: colors.danger, fontSize: 13, fontWeight: '700' },
});
