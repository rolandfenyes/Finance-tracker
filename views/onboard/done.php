<section class="max-w-2xl mx-auto">
  <div class="bg-white rounded-2xl shadow-glass p-6 text-center">
    <h1 class="text-2xl font-semibold">You're all set 🎉</h1>
    <p class="text-gray-600 mt-2">
      Your account has been set up. You can read a short tutorial to learn how to use the app.
    </p>

    <div class="mt-6 grid sm:grid-cols-1 gap-3">
      <a href="/tutorial" class="btn btn-primary w-full text-center">Read tutorial</a>

      <form method="post" action="/onboard/done" class="w-full">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      </form>
    </div>

    <p class="text-sm text-gray-500 mt-4">
      You can open the tutorial anytime from the top menu.
    </p>
  </div>
</section>
