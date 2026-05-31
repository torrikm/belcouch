import logging
import os
from telegram import (
    Update,
    ReplyKeyboardMarkup,
    InlineKeyboardMarkup,
    InlineKeyboardButton,
    ReplyKeyboardRemove
)
from telegram.ext import (
    ApplicationBuilder,
    CommandHandler,
    MessageHandler,
    filters,
    ContextTypes,
    ConversationHandler,
    CallbackQueryHandler
)
from dotenv import load_dotenv

# Логирование
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)

CHOOSING, CONFIRMATION = range(2)

CITY_RECOMMENDATIONS = {
    'Тусовки и активный отдых': {
        'город': 'Минск',
        'описание': 'Минск — столица, здесь всегда кипит жизнь: клубы, бары, концерты, фестивали.'
    },
    'Памятники войны': {
        'город': 'Брест',
        'описание': 'Брест — город-герой, обязательно посетите Брестскую крепость.'
    },
    'Замковый комплекс': {
        'город': 'Мир и Несвиж',
        'описание': 'Мир и Несвиж — уникальные замки ЮНЕСКО.'
    },
    'Исторические достопримечательности': {
        'город': 'Гродно',
        'описание': 'Гродно — старинная архитектура и богатая история.'
    },
    'Культурные мероприятия': {
        'город': 'Витебск',
        'описание': 'Витебск — родина Славянского базара.'
    },
    'Природа и спокойствие': {
        'город': 'Гомель',
        'описание': 'Гомель — парк и спокойная атмосфера.'
    },
    'Старинные улицы': {
        'город': 'Могилёв',
        'описание': 'Могилёв — уютные старые улицы.'
    }
}

interest_keyboard = [
    ['Тусовки и активный отдых', 'Памятники войны'],
    ['Замковый комплекс', 'Исторические достопримечательности'],
    ['Культурные мероприятия', 'Природа и спокойствие'],
    ['Старинные улицы']
]
interest_markup = ReplyKeyboardMarkup(interest_keyboard, resize_keyboard=True)

confirmation_keyboard = [['Да', 'Другое']]
confirmation_markup = ReplyKeyboardMarkup(confirmation_keyboard, resize_keyboard=True)


async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(
        "Привет! Я помогу подобрать город. Какой отдых вы хотите?",
        reply_markup=interest_markup
    )
    return CHOOSING


async def handle_choice(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_choice = update.message.text.strip()
    logging.info(f"RAW INPUT: {repr(user_choice)}")

    recommendation = CITY_RECOMMENDATIONS.get(user_choice)

    if not recommendation:
        await update.message.reply_text(
            "Я не распознал вариант. Пожалуйста, нажмите кнопку ниже.",
            reply_markup=interest_markup
        )
        return CHOOSING

    context.user_data['recommendation'] = recommendation

    await update.message.reply_text(
        f"Подходит город: {recommendation['город']}\n\n"
        f"{recommendation['описание']}\n\n"
        f"Вас устраивает?",
        reply_markup=confirmation_markup
    )

    return CONFIRMATION


async def handle_confirmation(update: Update, context: ContextTypes.DEFAULT_TYPE):
    answer = update.message.text

    if answer == "Да":
        recommendation = context.user_data.get('recommendation')

        keyboard = [
            [InlineKeyboardButton("Перейти на сайт", url="https://belcouch.by")],
            [InlineKeyboardButton("Выбрать заново", callback_data="return_to_start")]
        ]

        await update.message.reply_text(
            f"Отлично!\n\n{recommendation['город']}\n{recommendation['описание']}",
            reply_markup=InlineKeyboardMarkup(keyboard)
        )
        return CONFIRMATION

    elif answer == "Другое":
        await update.message.reply_text(
            "Попробуем снова:",
            reply_markup=interest_markup
        )
        return CHOOSING

    else:
        await update.message.reply_text(
            "Выберите 'Да' или 'Другое'",
            reply_markup=confirmation_markup
        )
        return CONFIRMATION


async def button_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()

    if query.data == "return_to_start":
        await query.message.reply_text(
            "Выберите тип отдыха:",
            reply_markup=interest_markup
        )
        return CHOOSING


async def cancel(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(
        "Опрос завершён.",
        reply_markup=ReplyKeyboardRemove()
    )
    return ConversationHandler.END


def main():
    load_dotenv()
    TOKEN = os.getenv('TELEGRAM_BOT_TOKEN')

    if not TOKEN:
        raise ValueError("TELEGRAM_BOT_TOKEN не задан")

    app = ApplicationBuilder().token(TOKEN).build()

    conv_handler = ConversationHandler(
        entry_points=[CommandHandler('start', start)],
        states={
            CHOOSING: [
                MessageHandler(filters.TEXT & ~filters.COMMAND, handle_choice),
            ],
            CONFIRMATION: [
                MessageHandler(filters.TEXT & ~filters.COMMAND, handle_confirmation),
                CallbackQueryHandler(button_callback, pattern="^return_to_start$")
            ],
        },
        fallbacks=[CommandHandler('cancel', cancel)],
    )

    app.add_handler(conv_handler)

    print("Бот запущен")
    app.run_polling()


if __name__ == '__main__':
    main()