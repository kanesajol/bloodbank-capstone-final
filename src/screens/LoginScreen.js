import { useState } from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  StatusBar,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import { API_BASE_URL } from '../config';
import PasswordInput from '../components/PasswordInput';

export default function LoginScreen({ onBack, onSignUp }) {
  const { signIn } = useAuth();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleLogin() {
    setError('');
    setLoading(true);
    try {
      await signIn(username.trim(), password);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.root}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <StatusBar barStyle="dark-content" backgroundColor="#fff" />
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.card}>
          <View style={styles.header}>
            <Image
              source={require('../../assets/caraga-hospital-seal.png')}
              style={styles.logo}
              accessibilityLabel="Caraga Regional Hospital seal"
            />
            <Text style={styles.eyebrow}>CARAGA REGIONAL HOSPITAL</Text>
            <Text style={styles.title}>Staff Login</Text>
            <Text style={styles.subtitle}>Sign in to your Blood Bank account</Text>
          </View>
          <Text style={styles.inputLabel}>Username</Text>
          <TextInput
            style={styles.input}
            placeholder="Enter your username"
            placeholderTextColor="#9b8a8a"
            autoCapitalize="none"
            autoCorrect={false}
            value={username}
            onChangeText={setUsername}
          />
          <Text style={styles.inputLabel}>Password</Text>
          <PasswordInput
            accessibilityLabel="Password"
            autoComplete="current-password"
            style={styles.input}
            placeholder="Enter your password"
            placeholderTextColor="#9b8a8a"
            value={password}
            onChangeText={setPassword}
          />
          {error ? <Text style={styles.error} accessibilityRole="alert">{error}</Text> : null}
          <Pressable
            accessibilityRole="button"
            style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}
            onPress={handleLogin}
            disabled={loading}
          >
            {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Sign In</Text>}
          </Pressable>
          <View style={styles.links}>
            <Text style={styles.linkPrompt}>New staff member?</Text>
            <Pressable
              accessibilityRole="button"
              onPress={onSignUp}
              hitSlop={8}
            >
              <Text style={styles.linkText}>Create an account</Text>
            </Pressable>
          </View>
          <Pressable
            accessibilityRole="button"
            onPress={onBack}
            style={styles.backButton}
            hitSlop={8}
          >
            <Text style={styles.backText}>Back to welcome</Text>
          </Pressable>
          <Text style={styles.apiHint}>API: {API_BASE_URL}</Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#fff',
    justifyContent: 'center',
  },
  content: {
    flexGrow: 1,
    justifyContent: 'center',
  },
  card: {
    flexGrow: 1,
    backgroundColor: '#fff',
    justifyContent: 'center',
    paddingHorizontal: 26,
    paddingVertical: 32,
  },
  header: {
    alignItems: 'center',
    marginBottom: 26,
  },
  logo: {
    width: 78,
    height: 78,
    marginBottom: 14,
    borderRadius: 39,
    borderWidth: 2,
    borderColor: '#171717',
  },
  eyebrow: {
    color: '#7a1010',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.4,
    textAlign: 'center',
  },
  title: {
    marginTop: 6,
    color: '#990000',
    fontSize: 27,
    fontWeight: '900',
  },
  subtitle: {
    marginTop: 6,
    color: '#5b5555',
    fontSize: 14,
    textAlign: 'center',
  },
  inputLabel: {
    marginBottom: 7,
    color: '#292222',
    fontSize: 13,
    fontWeight: '700',
  },
  input: {
    minHeight: 52,
    borderWidth: 1.5,
    borderColor: '#ead7d7',
    borderRadius: 13,
    backgroundColor: '#fffafa',
    paddingHorizontal: 14,
    paddingVertical: 13,
    marginBottom: 18,
    fontSize: 16,
    color: '#171717',
  },
  button: {
    backgroundColor: '#990000',
    borderRadius: 14,
    minHeight: 54,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 3,
    borderWidth: 1,
    borderColor: '#171717',
  },
  buttonPressed: {
    opacity: 0.85,
    transform: [{ scale: 0.99 }],
  },
  buttonText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 16,
  },
  error: {
    color: '#b42318',
    marginBottom: 12,
    fontSize: 13,
    lineHeight: 19,
  },
  links: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    alignItems: 'center',
    columnGap: 5,
    marginTop: 20,
  },
  linkPrompt: {
    color: '#5b5555',
    fontSize: 13,
  },
  apiHint: {
    marginTop: 18,
    fontSize: 10,
    color: '#94a3b8',
    textAlign: 'center',
  },
  backButton: {
    alignSelf: 'center',
    paddingTop: 16,
  },
  backText: {
    color: '#5b5555',
    fontSize: 12,
  },
  linkText: {
    color: '#990000',
    fontSize: 13,
    fontWeight: '800',
  },
});
